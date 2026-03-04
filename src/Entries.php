<?php

namespace CR;

use duncan3dc\Forker\Fork;

class Entries
{
    public Config $config;
    /** @var array<string, Content[]> */
    public array $entries = [];
    /** @var array<string, array<string, array<string, Content[]>>> */
    public array $tax = [];
    public int $totalEntries = 0;
    protected ?DB $db;
    protected ?PluginManager $pluginManager;

    public function __construct(Config $config, ?DB $db, ?PluginManager $pluginManager)
    {
        $this->config = $config;
        $this->db = $db;
        $this->pluginManager = $pluginManager;
    }

    public function getEntryCount(): int
    {
        return $this->totalEntries;
    }

    /**
     * @return Content[]
     */
    public function get(string $contentType): array
    {
        if (isset($this->entries[$contentType])) {
            return $this->entries[$contentType];
        }

        return [];
    }

    /**
     * @return string[]
     */
    public function getTaxTypes(string $contentType): array
    {
        if (isset($this->tax[$contentType])) {
            $values = array_keys($this->tax[$contentType]);
            sort($values);
            return $values;
        }

        return [];
    }

    /**
     * @return string[]
     */
    public function getTaxTerms(string $contentType, string $taxType): array
    {
        if (isset($this->tax[$contentType][$taxType])) {
            $values = array_keys($this->tax[$contentType][$taxType]);
            sort($values);
            return $values;
        }

        return [];
    }

    /**
     * @return Content[]
     */
    public function getTax(string $contentType, string $taxType, string $term): array
    {
        if (isset($this->tax[$contentType]) && isset($this->tax[$contentType][$taxType]) && isset($this->tax[$contentType][$taxType][$term])) {
            return $this->tax[$contentType][$taxType][$term];
        }

        return [];
    }

    /**
     * @return Content[]
     */
    public function getAll(): array
    {
        $allEntries = [];

        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            if (isset($this->entries[$contentType])) {
                $allEntries = array_merge($allEntries, $this->entries[$contentType]);
            }
        }

        return $allEntries;
    }

    public function loadAllDb(): void
    {
        $imageProcessor = new ImageProcessor($this->config);
        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            if (!isset($this->entries[$contentType])) {
                $this->entries[$contentType] = [];
                $this->tax[$contentType] = [];
            }

            LOG(sprintf(_i18n('core.class.entries.processing.loading'), $contentType), 1, Log::INFO);

            $result = $this->db->getContentType($contentType);
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $content = new Content($this->config, $contentType, $contentConfig);
                $content->slug = $row['slug'];
                $content->title = $row['title'];
                $content->description = $row['description'];
                $content->unique = $row['hash'];
                $content->html = $row['html'];
                $content->featuredImage = $row['featured'];
                $content->publishDate = strtotime($row['created_at']);
                $content->modifiedDate = strtotime($row['modified_at']);

                $content->markdownFile = CROSSROADS_CONTENT_DIR . '/' . $row['content_slug'];
                $content->markdownData = $row['markdown'] ?? '';

                $content->contentPath = $content->contentType . '/' . $content->slug;
                $content->className = $content->slug;
                $content->isDraft = !empty($row['draft']);

                $content->calculate();

                if ($content->isDraft && !$this->config->get('options.include_drafts', false)) {
                    $draftFile = CROSSROADS_PUBLIC_DIR . $content->relUrl;
                    if (file_exists($draftFile)) {
                        unlink($draftFile);
                        if (file_exists($draftFile . '.md')) {
                            unlink($draftFile . '.md');
                        }
                        LOG(sprintf(_i18n('core.class.entries.removing_draft'), $content->slug), 2, Log::INFO);
                    } elseif (file_exists($draftFile . '.md')) {
                        unlink($draftFile . '.md');
                    }
                    LOG(sprintf(_i18n('core.class.entries.skipping_draft'), $content->slug), 2, Log::INFO);
                    continue;
                }

                $tax = $this->db->getAllTaxForContent($row['id']);
                while ($taxRow = $tax->fetchArray(SQLITE3_ASSOC)) {
                    $content->taxonomy[$taxRow['tax']][] = $taxRow['term'];
                }

                if ($content->featuredImage) {
                    $content->featuredImageData = $imageProcessor->processImage($content, $content->featuredImage);
                }

                if (count($content->taxonomy)) {
                    foreach ($content->taxonomy as $tax => $terms) {
                        foreach ($terms as $term) {
                            $this->tax[$contentType][$tax][$term][] = $content;
                        }
                    }
                }

                $this->entries[$contentType][] = $content;
                $this->totalEntries++;
            }
        }
    }

    public function loadAll(): void
    {
        $imageProcessor = new ImageProcessor($this->config);

        // Collect all content entries that need image processing
        $entriesNeedingImages = [];

        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            if (!isset($this->entries[$contentType])) {
                $this->entries[$contentType] = [];
                $this->tax[$contentType] = [];
            }

            LOG(sprintf(_i18n('core.class.entries.processing.loading'), $contentType), 1, Log::INFO);

            $content_directory = \CROSSROADS_CONTENT_DIR . '/' . $contentType;

            $allMarkdownFiles = $this->_findMarkdownFiles($content_directory);
            if (count($allMarkdownFiles)) {
                foreach ($allMarkdownFiles as $markdownFile) {
                    LOG(sprintf(_i18n('core.class.entries.processing.content'), pathinfo($markdownFile, PATHINFO_FILENAME)), 2, Log::DEBUG);

                    $markdown = new Markdown();
                    if ($markdown->loadFile($markdownFile)) {
                        $content = new Content($this->config, $contentType, $contentConfig);
                        $content->slug = $this->getSlugFromName(pathinfo($markdownFile, PATHINFO_FILENAME));
                        $content->markdownFile = $markdownFile;
                        $content->markdownData = $markdown->rawMarkdown();
                        $content->html = $markdown->html();
                        $content->modifiedDate = filemtime($markdownFile);
                        $content->contentPath = $content->contentType . '/' . basename($content->markdownFile);
                        $content->modifiedDate = filemtime($content->markdownFile);
                        $content->unique = md5(basename($content->markdownFile));
                        $content->className = $content->slug;

                        if ($front = $markdown->frontMatter()) {
                            $content->title = $this->_findDataInFrontMatter(['title'], $front, $content->title);
                            $content->slug = $this->_findDataInFrontMatter(['slug'], $front, $content->slug);
                            $content->publishDate = strtotime($this->_findDataInFrontMatter(['date', 'publishDate'], $front, date('Y-m-d')));
                            $content->featuredImage = $this->_findDataInFrontMatter(['featuredImage', 'coverImage', 'heroImage'], $front, $content->featuredImage);
                            $content->description = $this->_findDataInFrontMatter(['description'], $front, $content->description);
                            $content->isDraft = (bool) $this->_findDataInFrontMatter(['draft', 'isDraft'], $front, false);

                            if (isset($contentConfig['taxonomy'])) {
                                foreach ($contentConfig['taxonomy'] as $tax => $variations) {
                                    $content->taxonomy[$tax] = $this->_findDataInFrontMatter($variations, $front, []);
                                    $content->taxonomy[$tax] = array_map(function ($e) {
                                        return Utils::cleanTerm($e);
                                    }, $content->taxonomy[$tax]);

                                    if (count($content->taxonomy[$tax]) == 0) {
                                        unset($content->taxonomy[$tax]);
                                    }
                                }
                            }
                        }

                        $content->originalHtml = $content->html;

                        $content->calculate();

                        if ($content->isDraft && !$this->config->get('options.include_drafts', false)) {
                            $draftFile = CROSSROADS_PUBLIC_DIR . $content->relUrl;
                            if (file_exists($draftFile)) {
                                unlink($draftFile);
                                if (file_exists($draftFile . '.md')) {
                                    unlink($draftFile . '.md');
                                }
                                LOG(sprintf(_i18n('core.class.entries.removing_draft'), $content->slug), 2, Log::INFO);
                            } elseif (file_exists($draftFile . '.md')) {
                                unlink($draftFile . '.md');
                            }
                            LOG(sprintf(_i18n('core.class.entries.skipping_draft'), $content->slug), 2, Log::INFO);
                            continue;
                        }

                        // Collect for parallel image processing
                        $entriesNeedingImages[] = $content;

                        if (!$content->description) {
                            $content->description = $content->excerpt(120, false);
                        }

                        if (count($content->taxonomy)) {
                            foreach ($content->taxonomy as $tax => $terms) {
                                foreach ($terms as $term) {
                                    $this->tax[$contentType][$tax][$term][] = $content;
                                }
                            }
                        }

                        $this->entries[$contentType][] = $content;
                        $this->totalEntries++;
                    }
                }
            }

            $this->entries[$contentType] = $this->pluginManager->processAll($this->entries[$contentType]);
        }

        // Process all images (parallel if possible)
        $this->_processAllImages($entriesNeedingImages, $imageProcessor);
    }

    /**
     * Process images for all entries, using parallel workers when available.
     *
     * @param Content[] $entries
     */
    private function _processAllImages(array $entries, ImageProcessor $imageProcessor): void
    {
        if (empty($entries)) {
            return;
        }

        $workerCount = Utils::getWorkerCount($this->config);

        if ($workerCount > 1 && count($entries) > $workerCount) {
            $chunks = array_chunk($entries, (int) ceil(count($entries) / $workerCount));

            // Suppress E_DEPRECATED from duncan3dc/fork-helper (upstream nullable param issue)
            $errorLevel = error_reporting(error_reporting() & ~E_DEPRECATED);
            $fork = new Fork();

            foreach ($chunks as $chunk) {
                $fork->call(function () use ($chunk): void {
                    // Clear log listeners in child to avoid file handle contention
                    Log::instance()->clearListeners();

                    // Each worker creates its own ImageProcessor (fresh GD state)
                    $workerProcessor = new ImageProcessor($this->config);

                    foreach ($chunk as $entry) {
                        $entry->processImages($workerProcessor);
                    }
                });
            }

            $fork->wait();
            error_reporting($errorLevel);

            // After workers have written files to disk, re-apply in parent
            // to update HTML with correct URLs/srcset
            foreach ($entries as $entry) {
                $entry->applyProcessedImages($imageProcessor);
            }
        } else {
            // Sequential fallback
            foreach ($entries as $entry) {
                $entry->processImages($imageProcessor);
            }
        }
    }

    private function getSlugFromName(string $name): string
    {
        if (preg_match('#(\d+-\d+-\d+)-(.*)#', $name, $match)) {
            $name = $match[2];
        }
        return $name;
    }

    private function _findDataInFrontMatter(array $fields, array $frontMatter, mixed $default): mixed
    {
        foreach ($fields as $field) {
            if (isset($frontMatter[$field])) {
                return $frontMatter[$field];
            }
        }

        return $default;
    }

    /**
     * @return string[]
     */
    private function _findMarkdownFiles(string $directory): array
    {
        return Utils::findAllFilesWithExtension($directory, 'md');
    }
}

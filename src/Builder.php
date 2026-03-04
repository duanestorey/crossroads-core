<?php

/*
    All code copyright (c) 2024 by Duane Storey - All rights reserved
    You may use, distribute and modify this code under the terms of GPL version 3.0 license or later
*/

namespace CR;

use duncan3dc\Forker\Fork;

class Builder
{
    public Config $config;
    public int $totalPages = 0;

    public ?TemplateEngine $templateEngine = null;
    public ?Entries $entries = null;
    public ?Theme $theme = null;
    public ?Menu $menu = null;
    public ?PluginManager $pluginManager = null;
    public ?Renderer $renderer = null;

    protected DB $db;

    public function __construct(Config $config, PluginManager $pluginManager, DB $db)
    {
        $this->config = $config;
        $this->pluginManager = $pluginManager;
        $this->db = $db;
    }

    public function run(): void
    {
        if (!is_dir(CROSSROADS_PUBLIC_DIR)) {
            mkdir(CROSSROADS_PUBLIC_DIR, 0755, true);
        }
        if (!is_dir(CROSSROADS_PUBLIC_DIR . '/assets')) {
            mkdir(CROSSROADS_PUBLIC_DIR . '/assets');
        }

        $this->_setupTheme();
        $this->_setupMenus();

        $this->templateEngine = new TemplateEngine($this->config);

        if ($this->theme->isChildTheme()) {
            $this->templateEngine->setTemplateDirs(
                [
                   CROSSROADS_LOCAL_THEME_DIR . '/' . $this->theme->getChildThemeName(),
                   CROSSROADS_CORE_DIR . '/themes/' . $this->theme->getParentThemeName(),
                ]
            );
        } else {
            if ($this->theme->isLocalTheme()) {
                $this->templateEngine->setTemplateDirs(
                    [
                        CROSSROADS_LOCAL_THEME_DIR . '/' . $this->theme->getChildThemeName(),
                    ]
                );
            } else {
                $this->templateEngine->setTemplateDirs(
                    [
                        CROSSROADS_CORE_DIR . '/themes/' . $this->config->get('site.theme'),
                    ]
                );
            }

        }

        $this->renderer = new Renderer($this->config, $this->templateEngine, $this->pluginManager, $this->menu, $this->theme);

        // load all content here
        $this->entries = new Entries($this->config, $this->db, $this->pluginManager);
        $this->entries->loadAll();

        // Collect all single-page render jobs
        $singlePageJobs = [];

        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            $entries = $this->entries->get($contentType);

            if ($entries) {
                LOG(sprintf(_i18n('core.build.generating.single'), $contentType), 1, Log::INFO);

                // Make the output directory for html
                Utils::mkdir(CROSSROADS_PUBLIC_DIR . '/' . $contentType);

                // Make the output directory for images
                $image_destination_path = CROSSROADS_PUBLIC_DIR . '/assets/' . $contentType;
                Utils::mkdir($image_destination_path);

                foreach ($entries as $entry) {
                    $singlePageJobs[] = [
                        'entry' => $entry,
                        'templates' => [$entry->contentType . '-single', $entry->contentType, 'index'],
                    ];
                }
            }
        }

        // Render single pages (parallel if possible)
        $this->_renderSinglePages($singlePageJobs);

        // Index, home, and taxonomy pages (sequential — fewer pages, shared pagination state)
        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            $entries = $this->entries->get($contentType);

            // process all content
            usort($entries, 'CR\cr_sort');

            if (isset($contentConfig['index']) && $contentConfig['index']) {
                LOG(sprintf(_i18n('core.build.generating.index'), $contentType), 1, Log::INFO);

                $this->totalPages += $this->renderer->renderIndexPage(
                    $entries,
                    $contentType,
                    '/' . $contentType,
                    ['index'],
                    Renderer::CONTENT
                );
            }

            if ($contentType == $this->config->get('site.home')) {
                LOG(sprintf(_i18n('core.build.generating.home'), $contentType), 1, Log::INFO);

                $this->totalPages += $this->renderer->renderIndexPage(
                    $entries,
                    $contentType,
                    '',
                    ['index'],
                    Renderer::HOME
                );
            }

            // tax
            $taxTypes = $this->entries->getTaxTypes($contentType);
            sort($taxTypes);
            if (count($taxTypes)) {
                foreach ($taxTypes as $taxType) {
                    $taxTerms = $this->entries->getTaxTerms($contentType, $taxType);

                    LOG(sprintf(_i18n('core.build.generating.tax'), $contentType . '/' . $taxType), 1, Log::INFO);

                    Utils::mkdir(CROSSROADS_PUBLIC_DIR . '/' . $contentType . '/' . $taxType);

                    foreach ($taxTerms as $term) {
                        Utils::mkdir(CROSSROADS_PUBLIC_DIR . '/' . $contentType . '/' . $taxType . '/' . $term);

                        LOG(sprintf(_i18n('core.build.generating.tax'), $contentType . '/' . $term), 2, Log::DEBUG);

                        $entries = $this->entries->getTax($contentType, $taxType, $term);

                        usort($entries, 'CR\cr_sort');

                        if (count($entries)) {
                            $this->totalPages += $this->renderer->renderIndexPage(
                                $entries,
                                $contentType,
                                '/' . $contentType . '/' . $taxType . '/' .
                                $term,
                                ['index'],
                                Renderer::TAXONOMY,
                                $taxType,
                                $term
                            );
                        }
                    }
                }
            }
        }

        $this->_writeRobots();
        $this->_writeSitemapXml();
        $this->_writeLlmsTxt();

        LOG(sprintf(_i18n('core.build.total'), $this->entries->getEntryCount(), $this->totalPages), 0, Log::INFO);

        LOG(_i18n('core.build.done'), 0, Log::INFO);
    }

    /**
     * @param array<array{entry: Content, templates: string[]}> $jobs
     */
    private function _renderSinglePages(array $jobs): void
    {
        if (empty($jobs)) {
            return;
        }

        $workerCount = Utils::getWorkerCount($this->config);

        // Warmup: render one page per unique template to compile Latte cache
        $warmedTemplates = [];
        $warmupJobs = [];
        $remainingJobs = [];

        foreach ($jobs as $job) {
            $templateKey = $job['templates'][0]; // e.g., "posts-single"
            if (!isset($warmedTemplates[$templateKey])) {
                $warmedTemplates[$templateKey] = true;
                $warmupJobs[] = $job;
            } else {
                $remainingJobs[] = $job;
            }
        }

        // Render warmup pages sequentially (compiles templates)
        foreach ($warmupJobs as $job) {
            LOG('Writing content for [' . $job['entry']->relUrl . ']', 2, Log::DEBUG);
            $this->renderer->renderSinglePage($job['entry'], $job['templates']);
            $this->totalPages++;
        }

        if (empty($remainingJobs)) {
            return;
        }

        // Fork workers for remaining pages
        if ($workerCount > 1 && count($remainingJobs) > $workerCount) {
            $chunks = array_chunk($remainingJobs, (int) ceil(count($remainingJobs) / $workerCount));

            // Suppress E_DEPRECATED from duncan3dc/fork-helper (upstream nullable param issue)
            $errorLevel = error_reporting(error_reporting() & ~E_DEPRECATED);
            $fork = new Fork();

            foreach ($chunks as $chunk) {
                $fork->call(function () use ($chunk): void {
                    // Clear log listeners in child to avoid file handle contention
                    Log::instance()->clearListeners();

                    foreach ($chunk as $job) {
                        $this->renderer->renderSinglePage($job['entry'], $job['templates']);
                    }
                });
            }

            $fork->wait();
            error_reporting($errorLevel);
            $this->totalPages += count($remainingJobs);
        } else {
            // Sequential fallback
            foreach ($remainingJobs as $job) {
                LOG('Writing content for [' . $job['entry']->relUrl . ']', 2, Log::DEBUG);
                $this->renderer->renderSinglePage($job['entry'], $job['templates']);
                $this->totalPages++;
            }
        }
    }

    private function _writeSitemapXml(): void
    {
        $sitemapXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $sitemapXml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            $entries = $this->entries->get($contentType);

            usort($entries, 'CR\cr_sort');

            foreach ($entries as $entry) {
                if ($entry->isDraft) {
                    continue;
                }
                $sitemapXml = $this->_addSitemapEntry($sitemapXml, $entry->url);
            }

            $taxTypes = $this->entries->getTaxTypes($contentType);
            if (count($taxTypes)) {
                foreach ($taxTypes as $taxType) {
                    $taxTerms = $this->entries->getTaxTerms($contentType, $taxType);
                    if (count($taxTerms)) {
                        $taxUrl = $this->config->get('site.url') . '/' . $contentType . '/' . $taxType;
                        foreach ($taxTerms as $term) {
                            $sitemapXml = $this->_addSitemapEntry($sitemapXml, $taxUrl . '/' . $term, 'monthly');
                        }
                    }
                }
            }
        }

        $sitemapXml = $this->_addSitemapEntry($sitemapXml, $this->config->get('site.url'), 'daily');

        $sitemapXml .= "</urlset>\n";

        file_put_contents(CROSSROADS_PUBLIC_DIR . '/sitemap.xml', $sitemapXml);

        LOG(sprintf(_i18n('core.build.writing'), 'sitemap.xml'), 1, Log::INFO);
    }

    private function _addSitemapEntry(string $sitemapXml, string $url, string $freq = 'weekly'): string
    {
        $sitemapXml .= "\t<url>\n";
        $sitemapXml .= "\t\t<loc>" . $url . "</loc>\n";
        $sitemapXml .= "\t\t<changefreq>" . $freq . "</changefreq>\n";
        $sitemapXml .= "\t</url>\n";

        return $sitemapXml;
    }

    private function _writeRobots(): void
    {
        // write robots
        $robots = "user-agent: *\ndisallow: /assets/css/\ndisallow: /assets/js/\nallow: /\n\nUser-agent: Twitterbot\nallow: /\nSitemap: " . $this->config->get('site.url') . '/sitemap.xml';
        file_put_contents(CROSSROADS_PUBLIC_DIR . '/robots.txt', $robots);

        LOG(sprintf(_i18n('core.build.writing'), 'robots.txt'), 1, Log::INFO);
    }

    private function _writeLlmsTxt(): void
    {
        $siteUrl = $this->config->get('site.url');
        $llms = '# ' . $this->config->get('site.name') . "\n\n";
        $llms .= '> ' . $this->config->get('site.description', '') . "\n";

        foreach ($this->config->get('content', []) as $contentType => $contentConfig) {
            $entries = $this->entries->get($contentType);
            if (!count($entries)) {
                continue;
            }

            usort($entries, 'CR\cr_sort');

            $llms .= "\n## " . ucwords($contentType) . "\n\n";

            $limit = ($contentType === $this->config->get('site.home')) ? 50 : 0;
            $count = 0;

            foreach ($entries as $entry) {
                if ($entry->isDraft) {
                    continue;
                }

                $mdUrl = $entry->url . '.md';
                $desc = '';
                if ($entry->description) {
                    $descText = trim(preg_replace('/\s+/', ' ', $entry->description));
                    if (mb_strlen($descText) > 200) {
                        $descText = mb_substr($descText, 0, 200) . '...';
                    }
                    $desc = ': ' . $descText;
                }
                $llms .= '- [' . $entry->title . '](' . $mdUrl . ')' . $desc . "\n";

                $count++;
                if ($limit > 0 && $count >= $limit) {
                    break;
                }
            }
        }

        file_put_contents(CROSSROADS_PUBLIC_DIR . '/llms.txt', $llms);

        LOG(sprintf(_i18n('core.build.writing'), 'llms.txt'), 1, Log::INFO);
    }

    private function _setupMenus(): void
    {
        $this->menu = new Menu();
        $this->menu->loadMenus();
    }

    private function _setupTheme(): void
    {
        $this->theme = new Theme(
            $this->config->get('site.theme'),
            CROSSROADS_CORE_DIR . '/themes',
            CROSSROADS_LOCAL_THEME_DIR
        );

        LOG(sprintf(_i18n('core.theme.load'), $this->theme->name()), 1, Log::INFO);

        if (!$this->theme->load()) {
            throw new ThemeException('Broken theme');
        }

        LOG(_i18n('core.theme.loaded'), 2, Log::INFO);

        $this->theme->processAssets();
    }
}

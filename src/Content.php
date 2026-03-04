<?php

namespace CR;

function cr_sort(Content $a, Content $b): int
{
    if ($a->publishDate == $b->publishDate) {
        return 0;
    }

    return ($b->publishDate < $a->publishDate) ? -1 : 1;
}

class Content
{
    // configuration data
    public Config $config;
    public ?array $contentConfig = null;
    public string $contentType = '';

    public string $title = '';
    public string $originalTitle = '';
    public int $publishDate = 0;
    public int $modifiedDate = 0;
    public string $url = '';
    public string $markdownFile = '';
    public string $markdownData = '';
    public string $html = '';
    public string $originalHtml = '';
    public string|false $featuredImage = false;
    public ?\stdClass $featuredImageData = null;
    public string $description = '';
    public string $slug = '';
    /** @var array<string, string[]> */
    public array $taxonomy = [];

    // Calculated fields
    /** @var array<string, array<string, string>> */
    public array $taxonomyLinks = [];
    public string $className = '';
    public string $readingTime = '';
    public int $words = 0;
    public string $relUrl = '';
    public string $unique = '';
    public string $modifiedHash = '';
    public bool $isDraft = false;
    /** @var \stdClass[] */
    public array $imageInfo = [];
    public string $contentPath = '';

    public function __construct(Config $config, string $contentType, ?array $contentConfig)
    {
        $this->config = $config;
        $this->contentConfig = $contentConfig;
        $this->contentType = $contentType;

        $this->publishDate = time();
    }

    public function calculate(): void
    {
        $this->words = str_word_count(strip_tags($this->html));
        $minutes = intdiv($this->words, 225);
        if ($minutes <= 1) {
            $this->readingTime = _i18n('core.class.entries.reading_time.s');
        } else {
            $this->readingTime = sprintf(_i18n('core.class.entries.reading_time.p'), $minutes);
        }

        if (isset($this->contentConfig['base'])) {
            $contentLink = Utils::fixPath($this->contentConfig['base']) . '/' . $this->slug . '.html';
        } else {
            $contentLink = '/' . $this->contentType . '/' . $this->slug . '.html';
        }

        $this->url = Utils::fixPath($this->config->get('site.url')) . $contentLink;
        $this->relUrl = $contentLink;

        if (count($this->taxonomy)) {
            foreach ($this->taxonomy as $tax => $terms) {
                foreach ($terms as $term) {
                    $this->taxonomyLinks[$tax][$term] = '/' . $this->contentType . '/' . $tax . '/' . $term;
                }
            }
        }
    }

    /**
     * Discover images in this entry's HTML and featured image.
     * Populates $this->imageTasks with file references but does NOT do I/O.
     * Returns the list of raw image file names found in the HTML.
     *
     * @return array<string, string> originalTag => imageFile
     */
    public function discoverImages(): array
    {
        $allImages = [];

        $regexp = '(<img[^>]+src=(?:\"|\')\K(.[^">]+?)(?=\"|\'))';

        if (preg_match_all("/$regexp/", $this->originalHtml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $images) {
                $allImages[$images[1]] = $images[0];
            }
        }

        return $allImages;
    }

    /**
     * Process images: find, convert, build responsive variants, update HTML.
     * This is the all-in-one convenience method (sequential).
     */
    public function processImages(?ImageProcessor $imageProcessor = null): void
    {
        if ($imageProcessor === null) {
            $imageProcessor = new ImageProcessor($this->config);
        }

        $allProcessedImages = [];

        if ($this->featuredImage) {
            $imageInfo = $imageProcessor->processImage($this, $this->featuredImage);
            if ($imageInfo) {
                $this->featuredImageData = $imageInfo;
            }
        }

        $allImages = $this->discoverImages();

        // we have a list of all images here
        $toFind = [];
        $toReplace = [];

        foreach ($allImages as $originalTag => $image) {
            $imageInfo = $imageProcessor->processImage($this, $image);
            if ($imageInfo) {
                $allProcessedImages[] = $imageInfo;

                if ($imageInfo->is_local) {
                    $newImageHtml = str_replace($image, $imageInfo->url, $originalTag);

                    if ($imageInfo->hasResponsive) {
                        $srcset = [];

                        foreach ($imageInfo->responsiveImages as $image) {
                            $srcset[] = $image->url . ' ' . $image->width . 'w';
                        }

                        $srcset_text = implode(',', $srcset);

                        $newImageHtml = str_replace('<img ', '<img loading="lazy" srcset="' . $srcset_text . '" ', $newImageHtml);
                    }

                    $toFind[] = $originalTag;
                    $toReplace[] = $newImageHtml;
                }
            }
        }

        $this->html = str_replace($toFind, $toReplace, $this->html);

        $this->imageInfo = $allProcessedImages;
    }

    /**
     * Apply processed images: re-read processed files and update HTML with URLs/srcset.
     * Called after parallel workers have written image files to disk.
     */
    public function applyProcessedImages(ImageProcessor $imageProcessor): void
    {
        $allProcessedImages = [];

        if ($this->featuredImage) {
            $imageInfo = $imageProcessor->processImage($this, $this->featuredImage);
            if ($imageInfo) {
                $this->featuredImageData = $imageInfo;
            }
        }

        $allImages = $this->discoverImages();

        $toFind = [];
        $toReplace = [];

        foreach ($allImages as $originalTag => $image) {
            $imageInfo = $imageProcessor->processImage($this, $image);
            if ($imageInfo) {
                $allProcessedImages[] = $imageInfo;

                if ($imageInfo->is_local) {
                    $newImageHtml = str_replace($image, $imageInfo->url, $originalTag);

                    if ($imageInfo->hasResponsive) {
                        $srcset = [];

                        foreach ($imageInfo->responsiveImages as $image) {
                            $srcset[] = $image->url . ' ' . $image->width . 'w';
                        }

                        $srcset_text = implode(',', $srcset);

                        $newImageHtml = str_replace('<img ', '<img loading="lazy" srcset="' . $srcset_text . '" ', $newImageHtml);
                    }

                    $toFind[] = $originalTag;
                    $toReplace[] = $newImageHtml;
                }
            }
        }

        $this->html = str_replace($toFind, $toReplace, $this->html);
        $this->imageInfo = $allProcessedImages;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function excerpt(int $length = 600, bool $includeEllipsis = true): string
    {
        $str = '';
        $words = explode(' ', strip_tags($this->html));

        $len = 0;
        for ($i = 0; $i < count($words); $i++) {
            $str = $str . $words[$i] . ' ';
            $len += mb_strlen($words[$i]) + 1;

            if ($len >= $length) {
                break;
            }
        }

        if ($includeEllipsis) {
            $str = $str . '...';
        }

        return rtrim($str);
    }
}

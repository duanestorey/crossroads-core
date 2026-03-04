<?php

namespace CR\Plugins;

use CR\Config;
use CR\Plugin;

class SeoPlugin extends Plugin
{
    public Config $config;

    private const LOCALE_MAP = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'fr' => 'fr_FR',
        'de' => 'de_DE',
        'it' => 'it_IT',
        'pt' => 'pt_BR',
        'nl' => 'nl_NL',
        'ja' => 'ja_JP',
        'zh' => 'zh_CN',
        'ko' => 'ko_KR',
    ];

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

    public function __construct(Config $config)
    {
        parent::__construct('seo');

        $this->config = $config;
    }

    public function processOne(mixed $content): mixed
    {
        return $content;
    }

    public function templateParamFilter(mixed $params): mixed
    {
        $meta = [];
        $robotsDirectives = [];

        if ($params->isSingle) {
            $meta = $this->buildSingleMeta($params);
        } else {
            [$meta, $robotsDirectives] = $this->buildIndexMeta($params);
        }

        $noai = $this->config->get('options.noai', false);
        if ($noai) {
            $robotsDirectives[] = 'noai';
            $robotsDirectives[] = 'noimageai';
        }

        if ($robotsDirectives) {
            $meta[] = '<meta name="robots" content="' . implode(', ', $robotsDirectives) . '">';
        }

        $params->page->seoMeta = implode("\n", $meta);

        return $params;
    }

    /** @return string[] */
    private function buildSingleMeta(\stdClass $params): array
    {
        $content = $params->content;
        $siteName = $this->config->get('site.name');
        $lang = $this->config->get('site.lang', 'en');
        $description = $this->esc($content->description);
        $pageTitle = $this->esc($params->page->title);
        $url = $this->esc($content->url);
        $locale = $this->resolveLocale($lang);

        $meta = [];
        $meta[] = '<link rel="canonical" href="' . $url . '">';
        $meta[] = '<meta name="description" content="' . $description . '">';
        $meta[] = '<meta name="author" content="' . $this->esc($siteName) . '">';
        $meta[] = '<meta property="og:title" content="' . $pageTitle . '">';
        $meta[] = '<meta property="og:description" content="' . $description . '">';
        $meta[] = '<meta property="og:url" content="' . $url . '">';
        $meta[] = '<meta property="og:type" content="article">';
        $meta[] = '<meta property="og:site_name" content="' . $this->esc($siteName) . '">';
        $meta[] = '<meta property="og:locale" content="' . $this->esc($locale) . '">';

        if ($content->featuredImageData) {
            $meta[] = '<meta property="og:image" content="' . $this->esc($content->featuredImageData->public_url) . '">';
            $meta[] = '<meta property="og:image:width" content="' . $this->esc($content->featuredImageData->width) . '">';
            $meta[] = '<meta property="og:image:height" content="' . $this->esc($content->featuredImageData->height) . '">';
        }

        $cardType = $content->featuredImageData ? 'summary_large_image' : 'summary';
        $meta[] = '<meta name="twitter:card" content="' . $cardType . '">';
        $meta[] = '<meta name="twitter:title" content="' . $pageTitle . '">';
        $meta[] = '<meta name="twitter:description" content="' . $description . '">';

        $handle = $this->extractTwitterHandle();
        if ($handle) {
            $meta[] = '<meta name="twitter:site" content="' . $this->esc($handle) . '">';
            $meta[] = '<meta name="twitter:creator" content="' . $this->esc($handle) . '">';
        }

        if ($content->featuredImageData) {
            $meta[] = '<meta name="twitter:image" content="' . $this->esc($content->featuredImageData->public_url) . '">';
        }

        $meta[] = $this->buildSingleJsonLd($content, $siteName);

        return $meta;
    }

    /** @return array{string[], string[]} */
    private function buildIndexMeta(\stdClass $params): array
    {
        $siteName = $this->config->get('site.name');
        $lang = $this->config->get('site.lang', 'en');
        $siteUrl = $this->config->get('site.url');
        $description = $this->esc($params->page->description ?? '');
        $pageTitle = $this->esc($params->page->title);
        $locale = $this->resolveLocale($lang);

        $pageUrl = $siteUrl;
        if (isset($params->pagination)) {
            $pageUrl = rtrim($siteUrl, '/') . '/' . ltrim($params->pagination->curPageLink, '/');
        }

        $meta = [];
        $meta[] = '<meta name="description" content="' . $description . '">';
        $meta[] = '<meta property="og:title" content="' . $pageTitle . '">';
        $meta[] = '<meta property="og:description" content="' . $description . '">';
        $meta[] = '<meta property="og:url" content="' . $this->esc($pageUrl) . '">';
        $meta[] = '<meta property="og:type" content="website">';
        $meta[] = '<meta property="og:site_name" content="' . $this->esc($siteName) . '">';
        $meta[] = '<meta property="og:locale" content="' . $this->esc($locale) . '">';

        $robotsDirectives = [];
        $isPaginated = isset($params->pagination) && $params->pagination->currentPage > 1;
        if ($isPaginated) {
            $robotsDirectives[] = 'noindex';
            $robotsDirectives[] = 'follow';
        }

        $isHome = $params->isHome ?? false;
        if ($isHome) {
            $meta[] = $this->buildWebSiteJsonLd($siteName, $siteUrl);
        }

        return [$meta, $robotsDirectives];
    }

    private function buildSingleJsonLd(object $content, string $siteName): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $content->title,
            'description' => $content->description,
            'url' => $content->url,
            'datePublished' => date('Y-m-d', $content->publishDate),
            'dateModified' => date('Y-m-d', $content->modifiedDate ?: $content->publishDate),
            'author' => [
                '@type' => 'Person',
                'name' => $siteName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $siteName,
            ],
        ];

        if ($content->featuredImageData) {
            $data['image'] = $content->featuredImageData->public_url;
        }

        return '<script type="application/ld+json">' . json_encode($data, self::JSON_FLAGS) . '</script>';
    }

    private function buildWebSiteJsonLd(string $siteName, string $siteUrl): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
        ];

        return '<script type="application/ld+json">' . json_encode($data, self::JSON_FLAGS) . '</script>';
    }

    private function extractTwitterHandle(): ?string
    {
        $social = $this->config->get('site.social', []);
        if (!is_array($social)) {
            return null;
        }

        $url = $social['x'] ?? $social['twitter'] ?? null;
        if (!$url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $username = trim($path, '/');
        if ($username === '') {
            return null;
        }

        return '@' . $username;
    }

    private function resolveLocale(string $lang): string
    {
        if (str_contains($lang, '_')) {
            return $lang;
        }

        return self::LOCALE_MAP[$lang] ?? $lang . '_' . strtoupper($lang);
    }

    private function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

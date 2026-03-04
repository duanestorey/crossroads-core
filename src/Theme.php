<?php

/*
    All code copyright (c) 2024 by Duane Storey - All rights reserved
    You may use, distribute and modify this code under the terms of GPL version 3.0 license or later
*/

namespace CR;

class Theme
{
    protected ?Config $themeConfig = null;
    protected ?Config $childThemeConfig = null;

    protected ?string $themeName = null;
    protected ?string $parentThemeName = null;

    protected ?string $coreThemeDir = null;
    protected ?string $localThemeDir = null;
    protected bool $isChildTheme = false;
    protected bool $isLocalTheme = false;

    protected ?string $primaryThemeDir = null;

    public function __construct(string $themeName, string $coreThemeDir, string $localThemeDir)
    {
        $this->themeName = $themeName;
        $this->coreThemeDir = $coreThemeDir;
        $this->localThemeDir = $localThemeDir;
    }

    public function name(): ?string
    {
        return $this->themeName;
    }

    public function isChildTheme(): bool
    {
        return $this->isChildTheme;
    }

    public function isLocalTheme(): bool
    {
        return $this->isLocalTheme;
    }

    public function getChildThemeName(): ?string
    {
        return $this->themeName;
    }

    public function getParentThemeName(): ?string
    {
        return $this->parentThemeName;
    }

    public function primaryThemeDir(): ?string
    {
        return $this->primaryThemeDir;
    }

    public function load(): bool
    {
        // check for local child theme
        if (file_exists($this->localThemeDir . '/'. $this->themeName . '/theme.yaml')) {
            $localThemeConfig = new Config(YAML::parse_file($this->localThemeDir . '/' . $this->themeName . '/theme.yaml', true));
            if ($localThemeConfig->get('theme.parent', false)) {
                // it has a parent
                $parentTheme = $localThemeConfig->get('theme.parent');

                if (file_exists($this->coreThemeDir . '/' . $parentTheme . '/theme.yaml') && file_exists($this->coreThemeDir . '/' . $parentTheme . '/index.latte')) {
                    $this->isChildTheme = true;
                    $this->childThemeConfig = $localThemeConfig;
                    $this->parentThemeName = $parentTheme;
                    $this->isLocalTheme = true;
                    $this->primaryThemeDir = $this->coreThemeDir . '/' . $parentTheme;

                    $this->themeConfig = new Config(YAML::parse_file($this->coreThemeDir . '/' .  $parentTheme . '/theme.yaml', true));

                    return true;
                }
            } else {
                // not a child theme, but may be a regular theme
                if (file_exists($this->localThemeDir . '/' . $this->themeName . '/index.latte')) {
                    $this->themeConfig = new Config(YAML::parse_file($this->localThemeDir . '/' .  $this->themeName . '/theme.yaml', true));
                    $this->isLocalTheme = true;
                    $this->primaryThemeDir = $this->localThemeDir . '/' . $this->themeName;

                    return true;
                }
            }
        }

        if (file_exists($this->coreThemeDir . '/' . $this->themeName . '/theme.yaml') && file_exists($this->coreThemeDir . '/' . $this->themeName . '/index.latte')) {
            $this->themeConfig = new Config(YAML::parse_file($this->coreThemeDir . '/' .  $this->themeName . '/theme.yaml', true));
            $this->primaryThemeDir = $this->coreThemeDir . '/' . $this->themeName;

            return true;
        }

        return false;
    }

    public function getAssetHash(): string
    {
        $parts = [];

        foreach ($this->themeConfig->get('theme.assets', []) as $destName => $sources) {
            $path = CROSSROADS_PUBLIC_DIR . '/assets/' . $destName;
            if (file_exists($path)) {
                $parts[] = md5_file($path);
            }
        }

        return substr(md5(implode('', $parts)), 0, 8);
    }

    protected function accumulateAssets(string $contentSoFar, string $actualFile): string
    {
        if (file_exists($actualFile)) {
            if (SASS::isSassFile($actualFile)) {
                $contentSoFar = $contentSoFar . "\n\n" . SASS::parseFile($actualFile);
                LOG(sprintf(_i18n('core.class.theme.adding'), $actualFile), 3, Log::DEBUG);
            } else {
                $contentSoFar = $contentSoFar . "\n\n" . file_get_contents($actualFile);
                LOG(sprintf(_i18n('core.class.theme.sass'), $actualFile), 3, Log::DEBUG);
            }
        } else {
            LOG(sprintf(_i18n('core.class.theme.no_source'), $actualFile), 3, Log::WARNING);
        }

        return $contentSoFar;
    }

    public function processAssets(): void
    {
        foreach ($this->themeConfig->get('theme.assets', []) as $destName => $sources) {
            LOG(sprintf(_i18n('core.class.theme.processing'), $destName), 2, Log::INFO);

            $content = '';
            foreach ($sources as $key => $source) {
                $actualFile = $this->primaryThemeDir . '/assets/' . $source;
                $content = $this->accumulateAssets($content, $actualFile);
            }

            LOG('Writing static file [' . $destName . ']', 4, Log::DEBUG);

            file_put_contents(CROSSROADS_PUBLIC_DIR . '/assets/' . $destName, $content);
        }

        foreach ($this->themeConfig->get('theme.images', []) as $imageFile) {
            if (file_exists($this->primaryThemeDir . '/assets/' . $imageFile)) {
                Utils::copyFile($this->primaryThemeDir . '/assets/' . $imageFile, CROSSROADS_PUBLIC_DIR . '/assets/' . pathinfo($imageFile, PATHINFO_BASENAME));
                LOG(sprintf(_i18n('core.class.theme.copying'), $imageFile, CROSSROADS_PUBLIC_DIR . '/assets/' . pathinfo($imageFile, PATHINFO_BASENAME)), 3, Log::DEBUG);
            }
        }
    }
}

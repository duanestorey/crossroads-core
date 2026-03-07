<?php

namespace CR;

class TemplateEngine
{
    public ?\Latte\Engine $latte = null;
    /** @var string[]|string */
    public string|array $templateDirs = '.';
    public Config $config;

    protected ?LatteFileLoader $fileLoader = null;

    /** @var array<string, bool> Cache of templateExists() results */
    private array $templateExistsCache = [];

    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->latte = new \Latte\Engine();
        $this->latte->setLocale($config->get('site.lang', 'en'));

        $latteCacheDir = CROSSROADS_SITE_DIR . '/.latte-cache';
        if (!is_dir($latteCacheDir)) {
            mkdir($latteCacheDir, 0755, true);
        }
        $this->latte->setTempDirectory($latteCacheDir);
        $this->fileLoader = new LatteFileLoader();
        $this->latte->setLoader($this->fileLoader);
    }

    /** @param string[] $templateDirs */
    public function setTemplateDirs(array $templateDirs): void
    {
        $this->templateDirs = $templateDirs;

        $this->fileLoader->setDirectories($templateDirs);
    }

    public function templateExists(string $templateName): bool
    {
        if (isset($this->templateExistsCache[$templateName])) {
            return $this->templateExistsCache[$templateName];
        }

        foreach ($this->templateDirs as $dir) {
            if (file_exists($dir . '/' . $templateName . '.latte')) {
                $this->templateExistsCache[$templateName] = true;
                return true;
            }
        }

        LOG(sprintf("Template file doesn't exist [%s]", $templateName), 2, Log::WARNING);

        $this->templateExistsCache[$templateName] = false;
        return false;
    }

    /** @param string|string[] $templates */
    public function locateTemplate(string|array $templates): string|false
    {
        if (!is_array($templates)) {
            $templates = [ $templates ];
        }

        foreach ($templates as $template) {
            if ($this->templateExists($template)) {
                return $template;
            }
        }

        return false;
    }

    public function render(string $templateFile, \stdClass $params): string
    {
        if (!$this->latte) {
            LOG('Template engine not initialized', 1, Log::ERROR);
            return '';
        }

        return $this->latte->renderToString($templateFile . '.latte', $params);
    }
}

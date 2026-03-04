<?php

namespace CR;

class PluginManager extends Plugin
{
    public Config $config;
    /** @var Plugin[] */
    public array $plugins = [];

    public function __construct(Config $config)
    {
        parent::__construct('manager');
        $this->config = $config;
    }

    public function installPlugin(Plugin $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    public function contentFilter(mixed $content): mixed
    {
        foreach ($this->plugins as $plugin) {
            try {
                $content = $plugin->contentFilter($content);
            } catch (\Exception $e) {
                LOG(sprintf('Plugin [%s] contentFilter error: %s', $plugin->name(), $e->getMessage()), 0, Log::ERROR);
            }
        }

        return $content;
    }

    public function templateParamFilter(mixed $params): mixed
    {
        foreach ($this->plugins as $plugin) {
            try {
                $params = $plugin->templateParamFilter($params);
            } catch (\Exception $e) {
                LOG(sprintf('Plugin [%s] templateParamFilter error: %s', $plugin->name(), $e->getMessage()), 0, Log::ERROR);
            }
        }

        return $params;
    }

    public function processOne(mixed $entry): mixed
    {
        foreach ($this->plugins as $plugin) {
            try {
                $entry = $plugin->processOne($entry);
            } catch (\Exception $e) {
                LOG(sprintf('Plugin [%s] processOne error: %s', $plugin->name(), $e->getMessage()), 0, Log::ERROR);
            }
        }

        return $entry;
    }

    /** @param array $entries */
    public function processAll(array $entries): array
    {
        foreach ($this->plugins as $plugin) {
            try {
                LOG(sprintf(_i18n('core.build.plugins.executing'), $plugin->name()), 2, Log::INFO);

                $entries = $plugin->processAll($entries);
            } catch (\Exception $e) {
                LOG(sprintf('Plugin [%s] processAll error: %s', $plugin->name(), $e->getMessage()), 0, Log::ERROR);
            }
        }

        return $entries;
    }
}

<?php

namespace CR;

class Menu
{
    public array $menuData = [];
    public ?string $menuFile = null;

    public function __construct()
    {
        $this->menuFile = \CROSSROADS_CONFIG_DIR . '/menus.yaml';
    }

    public function loadMenus(): void
    {
        if (file_exists($this->menuFile)) {
            $this->menuData = YAML::parse_file($this->menuFile);
        }

        $localFile = \CROSSROADS_CONFIG_DIR . '/menus.local.yaml';
        if (file_exists($localFile)) {
            $localData = YAML::parse_file($localFile);
            if ($localData) {
                $this->menuData = array_merge($this->menuData ?: [], $localData);
            }
        }
    }

    public function isAvailable(string $menuName): bool
    {
        return isset($this->menuData[ $menuName ]);
    }

    /** @return string[] */
    public function getAvailable(): array
    {
        $available = [];

        foreach ($this->menuData as $name => $data) {
            $available[] = $name;
        }

        return $available;
    }

    /** @return list<\stdClass>|false */
    public function build(string $menuName, string $currentPage): array|false
    {
        $menuData = false;
        if (isset($this->menuData[ $menuName ])) {
            $menuData = [];

            foreach ($this->menuData[ $menuName ] as $pageName => $pageSlug) {
                $menuItem = new \stdClass();
                $menuItem->name = $pageName;
                $menuItem->url = $pageSlug;
                $menuItem->isActive = false;

                if (Utils::fixPath($currentPage) == Utils::fixPath($pageSlug)) {
                    $menuItem->isActive = true;
                }

                $menuData[] = $menuItem;
            }
        }

        return $menuData;
    }
}

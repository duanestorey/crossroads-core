<?php

namespace CR;

class Menu
{
    public array $menuData = [];
    public ?string $menuFile = null;

    /** @var array<string, list<\stdClass>> Cached base menu items (without isActive) */
    private array $menuCache = [];
    /** @var array<string, string[]> Cached fixPath URLs per menu */
    private array $menuUrlCache = [];

    public function __construct()
    {
        $this->menuFile = \CROSSROADS_CONFIG_DIR . '/menus.yaml';
    }

    public function loadMenus(): void
    {
        if (file_exists($this->menuFile)) {
            $parsed = YAML::parse_file($this->menuFile);
            if ($parsed) {
                $this->menuData = $parsed;
            }
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
        if (!isset($this->menuData[$menuName])) {
            return false;
        }

        // Build and cache base menu items on first call
        if (!isset($this->menuCache[$menuName])) {
            $this->menuCache[$menuName] = [];
            $this->menuUrlCache[$menuName] = [];

            foreach ($this->menuData[$menuName] as $pageName => $pageSlug) {
                $menuItem = new \stdClass();
                $menuItem->name = $pageName;
                $menuItem->url = $pageSlug;
                $menuItem->isActive = false;

                $this->menuCache[$menuName][] = $menuItem;
                $this->menuUrlCache[$menuName][] = Utils::fixPath($pageSlug);
            }
        }

        // Clone cached items and set isActive via pre-computed URLs
        $fixedCurrentPage = Utils::fixPath($currentPage);
        $menuData = [];

        foreach ($this->menuCache[$menuName] as $i => $baseItem) {
            $item = clone $baseItem;
            if ($fixedCurrentPage === $this->menuUrlCache[$menuName][$i]) {
                $item->isActive = true;
            }
            $menuData[] = $item;
        }

        return $menuData;
    }
}

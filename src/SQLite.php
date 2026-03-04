<?php

namespace CR;

class SQLite
{
    protected \SQLite3 $sql;
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;

        if (!file_exists(CROSSROADS_DB_DIR)) {
            if (!file_exists(CROSSROADS_DB_DIR)) {
                mkdir(CROSSROADS_DB_DIR);
            }
        }

        $this->sql = new \SQLite3(CROSSROADS_DB_DIR . '/db.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
        $this->sql->exec('PRAGMA foreign_keys = ON');
    }

    public function __destruct()
    {
        $this->sql->close();
    }

    public function rebuild(): void
    {
        $schemaFiles = Utils::findAllFilesWithExtension(CROSSROADS_CORE_DIR . '/schemas', 'sql');
        if ($schemaFiles) {
            foreach ($schemaFiles as $schema) {
                $schemaContents = file_get_contents($schema);
                $this->sql->exec($schemaContents);
            }
        }
    }

    public function prepare(string $sql): \SQLite3Stmt|false
    {
        return $this->sql->prepare($sql);
    }

    public function query(string $sql): \SQLite3Result|false
    {
        return $this->sql->query($sql);
    }

    public function getLastRowID(): int
    {
        return $this->sql->lastInsertRowID();
    }
}

<?php

namespace CR;

class DB
{
    public SQLite $sql;
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->sql = new SQLite($config);
    }

    public function rebuild(): void
    {
        $this->sql->rebuild();
    }

    public function addContent(Content $content): void
    {
        $this->sql->query('BEGIN');

        try {
            $stmt = $this->sql->prepare(
                'INSERT INTO "content" (type, hash, rel_url, slug, html, title, description, featured, created_at, modified_at, content_slug, markdown, original_html, draft) VALUES (:type, :hash, :rel_url, :slug, :html, :title, :description, :featured, :created_at, :modified_at, :content_slug, :markdown, :original_html, :draft)'
            );
            $stmt->bindValue(':type', $content->contentType, SQLITE3_TEXT);
            $stmt->bindValue(':hash', $content->unique, SQLITE3_TEXT);
            $stmt->bindValue(':rel_url', $content->relUrl, SQLITE3_TEXT);
            $stmt->bindValue(':slug', $content->slug, SQLITE3_TEXT);
            $stmt->bindValue(':html', $content->html, SQLITE3_TEXT);
            $stmt->bindValue(':title', $content->title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $content->description, SQLITE3_TEXT);
            $stmt->bindValue(':featured', $content->featuredImage, SQLITE3_TEXT);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s', $content->publishDate), SQLITE3_TEXT);
            $stmt->bindValue(':modified_at', date('Y-m-d H:i:s', $content->modifiedDate), SQLITE3_TEXT);
            $stmt->bindValue(':content_slug', $content->contentPath, SQLITE3_TEXT);
            $stmt->bindValue(':markdown', $content->markdownData, SQLITE3_TEXT);
            $stmt->bindValue(':original_html', $content->originalHtml, SQLITE3_TEXT);
            $stmt->bindValue(':draft', $content->isDraft ? 1 : 0, SQLITE3_INTEGER);

            LOG(sprintf('Importing [%s]', $content->slug), 2, Log::DEBUG);

            $stmt->execute();
            $lastRow = $this->sql->getLastRowID();

            if (count($content->taxonomy)) {
                foreach ($content->taxonomy as $taxType => $terms) {
                    foreach ($terms as $term) {
                        $stmt = $this->sql->prepare(
                            'INSERT INTO "taxonomy" (type, tax, term, content_id) VALUES (:type, :tax, :term, :content_id)'
                        );
                        $stmt->bindValue(':type', $content->contentType, SQLITE3_TEXT);
                        $stmt->bindValue(':tax', $taxType, SQLITE3_TEXT);
                        $stmt->bindValue(':term', $term, SQLITE3_TEXT);
                        $stmt->bindValue(':content_id', $lastRow, SQLITE3_INTEGER);

                        LOG(sprintf('Importing tax/term [%s]', $taxType . '/' . $term), 2, Log::DEBUG);

                        $stmt->execute();
                    }
                }
            }

            if (count($content->imageInfo)) {
                foreach ($content->imageInfo as $image) {
                    $this->addImageToDb($image, $lastRow);

                    if (count($image->responsiveImages)) {
                        foreach ($image->responsiveImages as $size => $respImage) {
                            $this->addImageToDb($respImage, $lastRow, $image->url);
                        }
                    }
                }
            }

            $this->sql->query('COMMIT');
        } catch (\Throwable $e) {
            $this->sql->query('ROLLBACK');
            throw $e;
        }
    }

    protected function addImageToDb(object $image, int $id, string $respFile = ''): void
    {
        if ($image->is_local && $image->isValid) {
            $stmt = $this->sql->prepare(
                'INSERT INTO "images" (filename, width, height, resp_filename, content_id, mod_time) VALUES (:filename, :width, :height, :resp_filename, :content_id, :mod_time)'
            );
            $stmt->bindValue(':filename', $image->url, SQLITE3_TEXT);
            $stmt->bindValue(':width', $image->width, SQLITE3_INTEGER);
            $stmt->bindValue(':height', $image->height, SQLITE3_INTEGER);
            $stmt->bindValue(':resp_filename', $respFile, SQLITE3_TEXT);
            $stmt->bindValue(':content_id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':mod_time', $image->modificationTime, SQLITE3_INTEGER);

            $stmt->execute();
        }
    }

    public function getAllContent(): \SQLite3Result|false
    {
        return $this->sql->query('SELECT * FROM content');
    }

    public function getContentType(string $contentType): \SQLite3Result|false
    {
        $stmt = $this->sql->prepare('SELECT * FROM content WHERE type = :type');
        $stmt->bindValue(':type', $contentType, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getAllTaxForContent(int $contentId): \SQLite3Result|false
    {
        $stmt = $this->sql->prepare('SELECT tax, term FROM taxonomy WHERE content_id = :content_id');
        $stmt->bindValue(':content_id', $contentId, SQLITE3_INTEGER);
        return $stmt->execute();
    }

    public function getAllTerms(): \SQLite3Result|false
    {
        return $this->sql->query('SELECT * FROM taxonomy');
    }
}

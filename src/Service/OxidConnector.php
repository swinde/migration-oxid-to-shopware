<?php

declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Service;

use PDO;
use Psr\Log\LoggerInterface;

final class OxidConnector
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    public function getCategories(): array
    {
        $sql = "
            SELECT
                OXID          AS id,
                CASE
                    WHEN OXPARENTID IN ('oxrootid', '') THEN NULL
                    ELSE OXPARENTID
                END            AS parentId,
                OXTITLE       AS name,
                OXDESC        AS description,
                OXACTIVE      AS active,
                OXSORT        AS position,
                OXKEYWORDS    AS metaKeywords,
                OXLONGDESC    AS metaDescription
            FROM oxcategories
            ORDER BY OXLEFT
        ";

        try {
            $stmt = $this->pdo->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$categories) {
                $this->logger->warning('⚠️ Keine Kategorien aus OXID abgerufen.');
                return [];
            }

            // Debug-Ausgabe – legt JSON im Projektordner ab
            $previewPath = __DIR__ . '/../../categories_preview.json';
            file_put_contents(
                $previewPath,
                json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $this->logger->info('📝 Kategorien-Vorschau gespeichert unter ' . $previewPath);

            return $categories;
        } catch (\Throwable $e) {
            $this->logger->error('❌ Fehler beim Laden der Kategorien: ' . $e->getMessage());
            return [];
        }
    }

    // Alte Funktion darf bleiben, wenn andere Teile sie brauchen
    public function fetchCategories(): array
    {
        $sql = 'SELECT * FROM oxcategories';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php

namespace MigrationSwinde\MigrationOxidToShopware\Connectors;

use PDO;
use Psr\Log\LoggerInterface;

class OxidConnector
{
    public function __construct(
        private PDO $pdo,
        private LoggerInterface $logger
    ) {}

    /**
     * Holt alle Kategorien (ggf. mit Limit) aus der OXID-Datenbank.
     */
    public function fetchCategories(?int $limit = null): array
    {
        $sql = "
            SELECT 
                OXID,
                OXTITLE,
                OXPARENTID,
                OXACTIVE,
                OXLONGDESC,
                OXTHUMB,
                OXICON
            FROM oxcategories
            ORDER BY OXPARENTID, OXSORT
        ";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        try {
            $stmt = $this->pdo->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info(sprintf('Fetched %d Kategorien aus OXID.', count($categories)));
            return $categories;
        } catch (\Throwable $e) {
            $this->logger->error('Fehler beim Abrufen der Kategorien: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Holt eine einzelne Kategorie anhand ihrer OXID-ID.
     */
    public function getCategoryById(string $oxidId): ?array
    {
        $sql = "
            SELECT 
                OXID,
                OXTITLE,
                OXPARENTID,
                OXACTIVE,
                OXLONGDESC,
                OXTHUMB,
                OXICON
            FROM oxcategories
            WHERE OXID = :oxid
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['oxid' => $oxidId]);

        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        return $category ?: null;
    }

    /**
     * Gibt Kategorien als assoziatives Array zurück, indexiert nach OXID.
     */
    public function getCategoriesIndexed(): array
    {
        $indexed = [];

        // nutzt jetzt fetchCategories(), nicht mehr getCategories()
        foreach ($this->fetchCategories() as $cat) {
            $indexed[$cat['OXID']] = $cat;
        }

        $this->logger->info(sprintf('Indexed %d Kategorien nach OXID.', count($indexed)));
        return $indexed;
    }
}

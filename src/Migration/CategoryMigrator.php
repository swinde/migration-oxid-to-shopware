<?php

namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use MigrationSwinde\MigrationOxidToShopware\Service\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\ShopwareConnector;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryMigrator
{
    public function __construct(
        private OxidConnector $oxid,
        private ShopwareConnector $shopware,
        private string $mapFile,
        private LoggerInterface $logger
    ) {}

    /** OXID => Shopware-ID */
    private array $categoryCache = [];

    /** Neuer Einstiegspunkt (ersetzt dein bisheriges migrate()) */
    public function migrateAll(): void
    {
        $categories = $this->oxid->getCategoriesIndexed(); // neu: indexiert nach OXID

        // Root-Kategorien finden und rekursiv migrieren
        foreach ($categories as $cat) {
            if (empty($cat['OXPARENTID']) || $cat['OXPARENTID'] === 'oxrootid') {
                $this->migrateCategory($cat, $categories);
            }
        }

        $this->logger->info('Kategorienmigration abgeschlossen.');
    }

    /** Rekursive Migration einer Kategorie inkl. Parent-Auflösung */
    private function migrateCategory(array $category, array $all): void
    {
        $oxidId = $category['OXID'];

        if (isset($this->categoryCache[$oxidId])) {
            return; // bereits angelegt
        }

        // Parent sicherstellen (rekursiv)
        $parentId = null;
        $parentOxid = $category['OXPARENTID'] ?? null;
        if (!empty($parentOxid) && $parentOxid !== 'oxrootid') {
            if (!isset($this->categoryCache[$parentOxid]) && isset($all[$parentOxid])) {
                $this->migrateCategory($all[$parentOxid], $all);
            }
            $parentId = $this->categoryCache[$parentOxid] ?? null;
        }

        // Payload inkl. Beschreibung
        $payload = [
            'name'        => $category['OXTITLE'] ?? '',
            'description' => $category['OXDESC'] ?? '',
            'active'      => (int)($category['OXACTIVE'] ?? 1) === 1,
            'parentId'    => $parentId,
        ];

        try {
            $shopwareId = $this->shopware->createCategory($payload);
            $this->categoryCache[$oxidId] = $shopwareId;
            $this->logger->info(sprintf(
                "%s%sKategorie '%s' (OXID: %s, Shopware: %s)",
                $prefix,
                $arrow,
                $payload['name'],
                $oxidId,
                $shopwareId
            ));

            // Kinder migrieren
            foreach ($all as $child) {
                if (($child['OXPARENTID'] ?? null) === $oxidId) {
                    $this->migrateCategory($child, $all, $depth + 1);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("Fehler bei Kategorie {$oxidId}: " . $e->getMessage());
        }
    }
}


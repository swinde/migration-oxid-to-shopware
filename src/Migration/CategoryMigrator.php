<?php

namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use MigrationSwinde\MigrationOxidToShopware\Connectors\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Connectors\ShopwareConnector;
use Psr\Log\LoggerInterface;

class CategoryMigrator extends AbstractMigrator
{
    private array $migratedIds = []; // [oxidId => shopwareId]

    public function __construct(
        private OxidConnector $oxid,
        private ShopwareConnector $shopware,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Startet die rekursive Migration der kompletten Kategorie-Hierarchie.
     */
    public function migrateAll(?int $limit = null, bool $dryRun = null): void
    {
        if ($dryRun !== null) {
            $this->setDryRun($dryRun);
        }
        $categories = $this->oxid->getCategoriesIndexed();
        if ($limit !== null) {
            $categories = array_slice($categories, 0, $limit, true);
        }

        $roots = array_filter($categories, fn($c) =>
            empty($c['OXPARENTID']) || $c['OXPARENTID'] === 'oxrootid'
        );

        $this->log(sprintf(
            'Starte rekursive Migration von %d Root-Kategorien%s',
            count($roots),
            $this->isDryRun() ? ' (DRY-RUN)' : ''
        ));

        foreach ($roots as $root) {
            $this->migrateCategoryRecursive($root, $categories);
        }

        $this->log('Migration abgeschlossen' . ($this->isDryRun() ? ' (DRY-RUN – keine Daten geschrieben)' : ''));
    }

    /**
     * Rekursive Migration einer Kategorie und aller Unterkategorien.
     */
    private function migrateCategoryRecursive(array $cat, array $allCategories): void
    {
        $this->migrateCategory($cat);

        // Alle Kinder dieser Kategorie suchen
        foreach ($allCategories as $child) {
            if ($child['OXPARENTID'] === $cat['OXID']) {
                $this->migrateCategoryRecursive($child, $allCategories);
            }
        }
    }

    /**
     * Migriert eine einzelne Kategorie (mit Dry-Run-Schutz und Bild-Upload).
     */
    private function migrateCategory(array $cat): void
    {
        // Parent ermitteln
        $parentId = $this->mapParent($cat['OXPARENTID']);

        $payload = [
            'name'           => $cat['OXTITLE'] ?? 'Unbenannte Kategorie',
            'description'    => $cat['OXLONGDESC'] ?? '',
            'parentId'       => $parentId,
            'active'         => (bool)($cat['OXACTIVE'] ?? true),
            'salesChannelId' => $this->shopware->getSalesChannelId(),
        ];

        // 🧩 Bild hinzufügen (wenn vorhanden)
        if (!empty($cat['OXTHUMB'])) {
            if ($this->isDryRun()) {
                $this->log("[DRY-RUN] Kategorie-Bild würde hochgeladen: {$cat['OXTHUMB']}", true);
            } else {
                $mediaId = $this->shopware->uploadCategoryMedia($cat['OXTHUMB'], false);
                if (!empty($mediaId)) {
                    $payload['mediaId'] = $mediaId;
                }
            }
        }

        // 🧩 Leere Felder entfernen, damit Shopware keine Invalid UUID-Fehler wirft
        foreach (['parentId', 'salesChannelId', 'mediaId'] as $key) {
            if (empty($payload[$key])) {
                unset($payload[$key]);
            }
        }

        // 🧩 DRY-RUN → nur Log
        if ($this->isDryRun()) {
            $this->log("Kategorie '{$payload['name']}' würde erstellt werden.", true);
            return;
        }

        try {
            // 🧩 Kategorie anlegen
            $shopwareId = $this->shopware->createCategory($payload);
            $this->migratedIds[$cat['OXID']] = $shopwareId;
            $this->log("Kategorie '{$payload['name']}' erfolgreich erstellt (ID: {$shopwareId}).");
        } catch (\Throwable $e) {
            $this->logger->error("Fehler bei Kategorie {$cat['OXID']}: " . $e->getMessage());
        }
    }


    /**
     * Gibt die Shopware-ID der Parent-Kategorie zurück.
     */
    private function mapParent(?string $oxidParentId): ?string
    {
        if (empty($oxidParentId) || $oxidParentId === 'oxrootid') {
            return null;
        }

        return $this->migratedIds[$oxidParentId] ?? null;
    }
}

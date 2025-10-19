<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class MigrationSwindeMigrationOxidToShopware extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        // Optional: Tabellen oder Defaults initialisieren
    }
}

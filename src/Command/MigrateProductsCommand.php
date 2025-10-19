<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\OxidConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\ShopwareConnector;
use MigrationSwinde\MigrationOxidToShopware\Service\MediaUploader;
use MigrationSwinde\MigrationOxidToShopware\Helper\UUIDHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateProductsCommand extends Command
{
    protected static $defaultName = 'migration:oxid-products';

    private OxidConnector $oxid;
    private ShopwareConnector $shopware;

    public function __construct(OxidConnector $oxid, ShopwareConnector $shopware)
    {
        parent::__construct();
        $this->oxid = $oxid;
        $this->shopware = $shopware;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = $this->oxid->getProducts();
        $output->writeln("➡️  {$products}");

        foreach ($products as $product) {
            $uuid = UUIDHelper::uuid_create();
            $output->writeln("🔹 Produkt UUID: $uuid");
            // TODO: Produkt in Shopware anlegen + Media hochladen
        }

        return Command::SUCCESS;
    }
}

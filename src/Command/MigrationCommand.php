<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\ProductMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationCommand extends Command
{
    protected static $defaultName = 'migration:oxid';

    private ProductMigrator $migrator;

    public function __construct(ProductMigrator $migrator)
    {
        parent::__construct();
        $this->migrator = $migrator;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = $this->migrator->fetchProducts();

        foreach ($products as $product) {
            try {
                $uuid = $this->migrator->migrateProduct($product);
                $output->writeln("✅ Produkt migriert: {$product['OXTITLE']} (UUID: $uuid)");
            } catch (\Throwable $e) {
                $output->writeln("❌ Fehler beim Anlegen des Produkts: {$product['OXTITLE']}");
                $output->writeln("   " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}

<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\ProductMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Command name
#[AsCommand(name: 'migration:oxid')]
class MigrateOxidCommand extends Command
{
    //protected static $defaultName = 'migration:oxid';
    protected static $defaultDescription = 'Migriert Produkte von OXID zu Shopware 6';

    private ProductMigrator $productMigrator;

    public function __construct(ProductMigrator $productMigrator)
    {
        parent::__construct();
        $this->productMigrator = $productMigrator;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starte OXID → Shopware Migration...</info>');

        try {
            $this->productMigrator->migrateAllProducts($output);
        } catch (\Throwable $e) {
            $output->writeln('<error>💥 Migration fehlgeschlagen: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Migration abgeschlossen!</info>');

        return Command::SUCCESS;
    }
}
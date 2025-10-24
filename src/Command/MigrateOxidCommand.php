<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\CategoryMigrator;
use MigrationSwinde\MigrationOxidToShopware\Service\ProductMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migration:oxid')]
class MigrateOxidCommand extends Command
{
    protected static $defaultDescription = 'Migriert Kategorien und Produkte von OXID zu Shopware 6';

    private CategoryMigrator $categoryMigrator;
    private ProductMigrator $productMigrator;

    public function __construct(CategoryMigrator $categoryMigrator, ProductMigrator $productMigrator)
    {
        parent::__construct();
        $this->categoryMigrator = $categoryMigrator;
        $this->productMigrator = $productMigrator;
    }

    protected function configure(): void
    {
        $this
            ->addOption('only', null, InputOption::VALUE_OPTIONAL, 'Was soll migriert werden? (categories|products)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getOption('only');

        $output->writeln('<info>🚀 Starte OXID → Shopware Migration...</info>');

        try {
            switch ($mode) {
                case 'categories':
                    $output->writeln('<comment>📁 Nur Kategorien werden migriert...</comment>');
                    $this->categoryMigrator->migrateAllCategories();
                    break;

                case 'products':
                    $output->writeln('<comment>🧩 Nur Produkte werden migriert...</comment>');
                    $this->productMigrator->migrateAllProducts();
                    break;

                default:
                    $output->writeln('<comment>📦 Kategorien und Produkte werden migriert...</comment>');
                    $this->categoryMigrator->migrateAllCategories();
                    $this->productMigrator->migrateAllProducts();
                    break;
            }
        } catch (\Throwable $e) {
            $output->writeln("<error>💥 Migration fehlgeschlagen: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln('<info>✅ Migration abgeschlossen!</info>');
        return Command::SUCCESS;
    }
}

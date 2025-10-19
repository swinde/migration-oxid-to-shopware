<?php
declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\ProductMigrator;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migration:oxid',
    description: 'Migriert Produkte von OXID zu Shopware 6.'
)]
class MigrateOxidCommand extends Command
{
    private ProductMigrator $productMigrator;

    public function __construct(ProductMigrator $productMigrator)
    {
        parent::__construct();
        $this->productMigrator = $productMigrator;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧭 OXID → Shopware Migration wird gestartet...');

        try {
            $context = Context::createDefaultContext();
            $this->productMigrator->migrate($context);
            $io->success('✅ Migration erfolgreich abgeschlossen!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('💥 Migration fehlgeschlagen: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

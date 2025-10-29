<?php

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\CategoryMigratorFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migration:oxid',
    description: 'Migriert Kategorien aus OXID nach Shopware 6'
)]
final class MigrateOxidCommand extends Command
{
    private CategoryMigratorFactory $factory;
    private LoggerInterface $logger;

    public function __construct(CategoryMigratorFactory $factory, LoggerInterface $logger)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧩 Migration OXID → Shopware 6');

        try {
            // Factory erstellt automatisch CategoryMigrator mit allen Abhängigkeiten
            $migrator = $this->factory->create();

            $io->section('🚀 Starte Kategorie-Migration …');
            $this->logger->info('🚀 Starte Kategorie-Migration via Console-Command.');

            $migrator->migrate();

            $io->success('✅ Kategorie-Migration abgeschlossen!');
            $this->logger->info('✅ Kategorie-Migration erfolgreich abgeschlossen.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('❌ Migration fehlgeschlagen: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error('Migration fehlgeschlagen: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

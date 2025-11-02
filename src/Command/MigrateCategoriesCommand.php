<?php

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Migration\CategoryMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:migrate:categories',
    description: 'Migriert OXID-Kategorien nach Shopware (mit optionalem Dry-Run)'
)]
class MigrateCategoriesCommand extends Command
{
    public function __construct(private CategoryMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Führt die Migration ohne API-Aufrufe durch (nur Logs).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Begrenzt die Anzahl der zu migrierenden Kategorien.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption('dry-run');
        $limit = $input->getOption('limit') ? (int)$input->getOption('limit') : null;

        $output->writeln(
            $isDryRun
                ? '<comment>Starte Kategorie-Migration im DRY-RUN-Modus...</comment>'
                : '<info>Starte echte Kategorie-Migration...</info>'
        );

        try {
            $this->migrator->migrateAll($limit, $isDryRun);
            $this->migrator->migrateAll($limit);
            $output->writeln('<info>Migration abgeschlossen 🎉</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Fehler: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}

<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;


// Command name
#[AsCommand(name: 'migration:oxid')]
class MigrationCommand extends Command
{
    //protected static $defaultName = 'migration:oxid'; // <-- hier der Name

    protected function configure(): void
    {
        $this->setDescription('Migrates products from OXID to Shopware');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Migration gestartet...');
        // hier deinen Migration-Code aufrufen

        return Command::SUCCESS;
    }
}

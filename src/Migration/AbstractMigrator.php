<?php
namespace MigrationSwinde\MigrationOxidToShopware\Migration;

use Psr\Log\LoggerInterface;

abstract class AbstractMigrator
{
    protected bool $dryRun = false;

    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    protected function log(string $message, bool $isDryRun = false): void
    {
        $prefix = $isDryRun ? '[DRY-RUN] ' : '';
        $this->logger->info($prefix . $message);
    }

    protected function isDryRun(): bool
    {
        return $this->dryRun;
    }
}

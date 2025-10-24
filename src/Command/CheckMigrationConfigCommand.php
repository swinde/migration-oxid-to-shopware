<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationOxidToShopware\Command;

use MigrationSwinde\MigrationOxidToShopware\Service\ProductMigratorFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migration:check-config', description: 'Zeigt aktuelle Konfiguration für OXID und Shopware Migration')]
class CheckMigrationConfigCommand extends Command
{
    private ProductMigratorFactory $factory;

    public function __construct(ProductMigratorFactory $factory)
    {
        parent::__construct();
        $this->factory = $factory;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('getConfigValue');
        $method->setAccessible(true);

        $output->writeln("<info>🔍 Aktuelle Migrationskonfiguration:</info>\n");

        $keys = [
            'oxidHost' => 'OXID_DB_HOST',
            'oxidPort' => 'OXID_DB_PORT',
            'oxidDatabase' => 'OXID_DB_NAME',
            'oxidUser' => 'OXID_DB_USER',
            'oxidPassword' => 'OXID_DB_PASS',
            'oxidImageBasePath' => 'OXID_IMAGE_BASE_PATH',
            'apiUrl' => 'SHOPWARE_API_URL',
            'accessKeyId' => 'SHOPWARE_ACCESS_KEY_ID',
            'accessKeySecret' => 'SHOPWARE_ACCESS_KEY_SECRET',
        ];

        foreach ($keys as $configKey => $envKey) {
            $value = $method->invoke($this->factory, $configKey, $envKey, '[kein Wert]');
            $masked = str_contains(strtolower($configKey), 'password') || str_contains(strtolower($configKey), 'secret')
                ? '********'
                : $value;

            $output->writeln(sprintf("  <comment>%s</comment>: %s", $configKey, $masked));
        }

        $output->writeln("\n<info>✅ Prüfung abgeschlossen.</info>");
        return Command::SUCCESS;
    }
}

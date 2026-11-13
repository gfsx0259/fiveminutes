<?php

declare(strict_types=1);

namespace App\Command;

use Beeterty\ClickHouse\Client;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Aliases\Aliases;

#[AsCommand('clickhouse:migrate', 'Creates the leads table')]
final class ClickHouseMigrateCommand extends Command
{
    public function __construct(
        private readonly Client $clickhouse,
        private readonly Aliases $aliases,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = file_get_contents($this->aliases->get('@root/clickhouse/leads.sql'));
        if ($sql === false || $sql === '') {
            throw new RuntimeException('ClickHouse schema file is missing.');
        }

        $this->clickhouse->execute($sql);
        $output->writeln('leads');

        return Command::SUCCESS;
    }
}

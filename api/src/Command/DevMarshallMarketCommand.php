<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:marshall-market', 'Stage demo: Marshall presets, orders, leads and stock')]
final class DevMarshallMarketCommand extends Command
{
    public function __construct(private readonly DevMarshallMarketFixture $fixture)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fixture->seed($output);

        return Command::SUCCESS;
    }
}
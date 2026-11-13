<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:seed', 'Development fixture. Seeds Chase and Marshall and their buyer and partner invites')]
final class DevSeedCommand extends Command
{
    public function __construct(private readonly DevOrganizationFixture $fixture)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fixture->seed('Chase', 'chase.io', $output);
        $this->fixture->seed('Marshall', 'marshall.io', $output);

        return Command::SUCCESS;
    }
}

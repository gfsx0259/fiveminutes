<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Lead\LiveDayInjectRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('live-day:inject', 'Publishes scheduled live shelf leads into day orders')]
final class LiveDayInjectCommand extends Command
{
    public function __construct(private readonly LiveDayInjectRunner $runner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Live day inject');
        while (true) {
            $count = $this->runner->publishDue();
            if ($count > 0) {
                $output->writeln(sprintf('Published %d live lead(s)', $count));
            }
            usleep(200_000);
        }
    }
}
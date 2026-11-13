<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Lead\LeadInbox;
use App\Context\Lead\Sale;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('lead:match', 'Sells a mined lead or leaves it in stock')]
final class MatchLeadsCommand extends Command
{
    public function __construct(
        private readonly LeadInbox $inbox,
        private readonly Sale $sale,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Matching leads');
        while (true) {
            try {
                $lead = $this->inbox->pull();
            } catch (InvalidArgumentException $exception) {
                $output->writeln($exception->getMessage());
                continue;
            }

            if ($lead === null) {
                usleep(200_000);
                continue;
            }

            try {
                $stored = $this->sale->place($lead);
            } catch (Throwable $exception) {
                $output->writeln($exception->getMessage());

                return Command::FAILURE;
            }

            $this->inbox->ack();
            $output->writeln($stored->getUuid() . ' ' . $stored->getStatus()->value);
        }
    }
}

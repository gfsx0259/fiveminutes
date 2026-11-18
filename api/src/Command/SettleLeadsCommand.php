<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Wallet\SettlementAccumulator;
use App\Context\Wallet\SettlementInbox;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('wallet:settle', 'Apply partner, buyer and org balances from settlement queue')]
final class SettleLeadsCommand extends Command
{
    public function __construct(
        private readonly SettlementInbox $inbox,
        private readonly SettlementAccumulator $accumulator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Consuming lead settlements');
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->accumulator->onShutdown());
        pcntl_signal(SIGINT, fn () => $this->accumulator->onShutdown());

        while (true) {
            try {
                $item = $this->inbox->pull();
            } catch (InvalidArgumentException $exception) {
                $output->writeln($exception->getMessage());
                continue;
            }

            if ($item === null) {
                $this->accumulator->onIdle();
                usleep(200_000);
                continue;
            }

            try {
                $this->accumulator->add($item);
            } catch (Throwable $exception) {
                $output->writeln($exception->getMessage());

                return Command::FAILURE;
            }

            $output->writeln($item->payload->leadUuid . ' ' . $item->payload->kind->value);
        }
    }
}
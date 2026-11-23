<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock;
use App\Context\Lead\LeadRepository;
use DateInterval;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'dev:shift-shelf-age',
    'Backdate created_at on recent in-stock leads so they leave the live window (dev/stage only)',
)]
final class DevShiftLeadShelfAgeCommand extends Command
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'How many latest in-stock leads', '10')
            ->addOption('minutes', 'm', InputOption::VALUE_REQUIRED, 'Subtract minutes from created_at', '45')
            ->addOption('organization', 'o', InputOption::VALUE_REQUIRED, 'Organization uuid filter', '')
            ->addOption('country', 'c', InputOption::VALUE_REQUIRED, 'Country code filter (e.g. ES)', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List leads without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $minutes = max(1, (int) $input->getOption('minutes'));
        $organizationId = trim((string) $input->getOption('organization'));
        $country = trim((string) $input->getOption('country'));
        $dryRun = (bool) $input->getOption('dry-run');

        $items = $this->leads->findLatestInStock(
            $limit,
            $organizationId === '' ? null : $organizationId,
            $country === '' ? null : $country,
        );
        if ($items === []) {
            $output->writeln('No matching in_stock leads.');

            return Command::SUCCESS;
        }

        $shift = new DateInterval('PT' . $minutes . 'M');
        $now = $this->clock->now();

        foreach ($items as $lead) {
            $old = $lead->getCreatedAt();
            $newCreated = $old->sub($shift);
            $output->writeln(sprintf(
                '%s %s/%s created %s → %s (age ~%d min)',
                $lead->getUuid(),
                $lead->getCountry(),
                $lead->getTrafficSource(),
                $old->format(DATE_ATOM),
                $newCreated->format(DATE_ATOM),
                $minutes,
            ));
            if ($dryRun) {
                continue;
            }
            $updated = $lead->withCreatedAt($newCreated, $now);
            $this->leads->add($updated);
        }

        if ($dryRun) {
            $output->writeln(sprintf('Dry run: %d lead(s), would shift back %d minutes.', count($items), $minutes));
        } else {
            $output->writeln(sprintf('Shifted %d lead(s) back by %d minutes.', count($items), $minutes));
        }

        return Command::SUCCESS;
    }
}
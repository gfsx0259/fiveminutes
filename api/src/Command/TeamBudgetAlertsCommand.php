<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Team\TeamBudget;
use App\Context\Team\TeamUnitRepository;
use App\Context\User\User;
use App\Context\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('team:budget-alerts', 'Warns team leads when a team budget reaches 80% and 100%')]
final class TeamBudgetAlertsCommand extends Command
{
    public function __construct(
        private readonly TeamUnitRepository $units,
        private readonly UserRepository $users,
        private readonly TeamBudget $budget,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Check every budget once and exit');
        $this->addOption('every', null, InputOption::VALUE_REQUIRED, 'Seconds between checks', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Team budget alerts');
        $pause = max(5, (int) $input->getOption('every'));
        do {
            $sent = 0;
            foreach ($this->units->budgetedAccountIds() as $accountId) {
                $account = $this->users->findByPK($accountId);
                if ($account instanceof User) {
                    $sent += $this->budget->alert($account);
                }
            }
            if ($sent > 0) {
                $output->writeln(sprintf('Sent %d budget alert(s)', $sent));
            }
            if ($input->getOption('once')) {
                return Command::SUCCESS;
            }
            sleep($pause);
        } while (true);
    }
}

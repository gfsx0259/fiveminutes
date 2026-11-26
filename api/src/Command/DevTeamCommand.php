<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Organization\OrganizationRepository;
use App\Context\Team\BudgetPeriod;
use App\Context\Team\MemberRepository;
use App\Context\Team\Seat;
use App\Context\Team\TeamBudget;
use App\Context\Team\TeamDesk;
use App\Context\Team\TeamRole;
use App\Context\Team\TeamTree;
use App\Context\Team\TeamUnit;
use App\Context\User\User;
use App\Context\User\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('dev:team', 'Builds a demo team tree for the partner of an organization')]
final class DevTeamCommand extends Command
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly MemberRepository $members,
        private readonly TeamDesk $team,
        private readonly TeamBudget $budget,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apex', null, InputOption::VALUE_REQUIRED, 'Organization apex', 'marshall.io');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apex = (string) $input->getOption('apex');
        $organization = $this->organizations->findByApex($apex);
        $partner = $this->users->findByEmail('partner@' . $apex);
        if ($organization === null || !$partner instanceof User) {
            $output->writeln('partner@' . $apex . ' has not signed up yet');

            return Command::FAILURE;
        }

        $owner = new Seat($partner);
        $crypto = $this->unit($owner, null, 'Crypto');
        $cryptoEu = $this->unit($owner, $crypto->getId(), 'Crypto EU');
        $gambling = $this->unit($owner, null, 'Gambling');
        $this->budget->limit($owner, $crypto->getId(), 1_000_000, BudgetPeriod::Month);
        $this->budget->limit($owner, $cryptoEu->getId(), 400_000, BudgetPeriod::Week);

        foreach ([
            ['lead.crypto@' . $apex, $crypto, TeamRole::Full],
            ['buyer.eu@' . $apex, $cryptoEu, TeamRole::Edit],
            ['finance@' . $apex, null, TeamRole::Finance],
            ['buyer.gambling@' . $apex, $gambling, TeamRole::Edit],
        ] as [$email, $unit, $role]) {
            if ($this->members->findByEmail($email) === null) {
                $this->team->invite($owner, $unit?->getId(), $email, $role->permissions());
                $output->writeln(sprintf('invite %s %s', $email, $role->label()));
            }
        }

        $output->writeln('team ready for partner@' . $apex);

        return Command::SUCCESS;
    }

    private function unit(Seat $owner, ?string $parentId, string $name): TeamUnit
    {
        $existing = $this->find($this->team->tree($owner), $parentId, $name);

        return $existing ?? $this->team->createUnit($owner, $parentId, $name);
    }

    private function find(TeamTree $tree, ?string $parentId, string $name): ?TeamUnit
    {
        foreach ($tree->children($parentId) as $unit) {
            if (mb_strtolower($unit->getName()) === mb_strtolower($name)) {
                return $unit;
            }
        }

        return null;
    }
}

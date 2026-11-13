<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Organization\OrganizationFactory;
use App\Context\Organization\OrganizationRepository;
use App\Context\User\Invitation;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:open-chase', 'Opens the Chase organization and its buyer and partner invites')]
final class OpenChaseCommand extends Command
{
    public function __construct(
        private OrganizationRepository $organizations,
        private OrganizationFactory $organizationFactory,
        private Invitation $invitation,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $organization = $this->organizations->findByApex('chase.io');
        if ($organization === null) {
            $organization = $this->organizationFactory->create('Chase', 'chase.io');
            $this->entityManager->persist($organization);
            $this->entityManager->run();
            $output->writeln('organization chase.io');
        }

        $this->invitation->open($organization, 'buyer@chase.io', UserRole::Buyer);
        $this->invitation->open($organization, 'partner@chase.io', UserRole::Partner);
        $output->writeln('invite buyer@chase.io buyer');
        $output->writeln('invite partner@chase.io partner');

        return Command::SUCCESS;
    }
}

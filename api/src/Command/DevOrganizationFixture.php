<?php

declare(strict_types=1);

namespace App\Command;

use App\Context\Organization\OrganizationFactory;
use App\Context\Organization\OrganizationRepository;
use App\Context\User\Invitation;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class DevOrganizationFixture
{
    public function __construct(
        private OrganizationRepository $organizations,
        private OrganizationFactory $organizationFactory,
        private Invitation $invitation,
        private EntityManagerInterface $entityManager,
    ) {}

    public function seed(string $name, string $apex, OutputInterface $output): void
    {
        $organization = $this->organizations->findByApex($apex);
        if ($organization === null) {
            $organization = $this->organizationFactory->create($name, $apex);
            $this->entityManager->persist($organization);
            $this->entityManager->run();
            $output->writeln('organization ' . $apex);
        }

        $buyer = 'buyer@' . $apex;
        $partner = 'partner@' . $apex;
        $admin = 'admin@' . $apex;
        $this->invitation->open($organization, $buyer, UserRole::Buyer);
        $this->invitation->open($organization, $partner, UserRole::Partner);
        $this->invitation->open($organization, $admin, UserRole::Admin);
        $output->writeln('invite ' . $buyer . ' buyer');
        $output->writeln('invite ' . $partner . ' partner');
        $output->writeln('invite ' . $admin . ' admin');
    }
}

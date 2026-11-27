<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use DomainException;

final readonly class RotationDesk
{
    public function __construct(
        private OrganizationRepository $organizations,
        private PresetRepository $presets,
        private OrderRepository $orders,
        private UserRepository $users,
        private RotationPicture $picture,
    ) {}

    /**
     * @return array{axes: list<string>, nodes: list<array<string, mixed>>}
     */
    public function picture(User $user): array
    {
        if ($user->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can see the rotation');
        }

        $organization = $this->organizations->findById($user->getOrganizationId());
        if ($organization === null) {
            throw new DomainException('Organization not found');
        }

        $emails = [];
        foreach ($this->users->findByOrganization($user->getOrganizationId()) as $member) {
            $emails[$member->getId()] = $member->getEmail();
        }

        $axes = $organization->getRotationAxes();

        return [
            'axes' => $axes->toList(),
            'nodes' => $this->picture->nodes(
                $axes,
                $this->presets->findByOrganization($user->getOrganizationId()),
                $this->orders->findByOrganization($user->getOrganizationId()),
                $emails,
            ),
        ];
    }
}

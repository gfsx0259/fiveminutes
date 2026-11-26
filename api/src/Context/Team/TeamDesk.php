<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\InviteRepository;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use App\Value\Email;
use Cycle\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

final readonly class TeamDesk
{
    public function __construct(
        private MemberRepository $members,
        private MemberFactory $memberFactory,
        private TeamUnitRepository $units,
        private TeamUnitFactory $unitFactory,
        private OrderPlacementRepository $placements,
        private UserRepository $users,
        private InviteRepository $invites,
        private TeamBudget $budget,
        private TeamJournal $journal,
        private TeamMailer $mailer,
        private TeamLinks $links,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    public function tree(Seat $seat): TeamTree
    {
        return $this->units->treeOf($this->account($seat)->getId());
    }

    /**
     * @return list<Member>
     */
    public function members(Seat $seat, TeamTree $tree): array
    {
        return array_values(array_filter(
            $this->members->findByAccount($this->account($seat)->getId()),
            static fn (Member $member): bool => $tree->covers($seat->unitId(), $member->getUnitId()),
        ));
    }

    public function managesMember(Seat $seat, TeamTree $tree, Member $member): bool
    {
        if ($seat->isOwner()) {
            return true;
        }
        if (!$seat->manages() || $member->getId() === $seat->getMember()?->getId()) {
            return false;
        }

        return $tree->below($seat->unitId(), $member->getUnitId())
            || ($member->getUnitId() === $seat->unitId() && !$member->getPermissions()->allows(Permission::TeamManage));
    }

    public function managesUnit(Seat $seat, TeamTree $tree, string $unitId): bool
    {
        return $seat->isOwner() || ($seat->manages() && $tree->below($seat->unitId(), $unitId));
    }

    public function buildsIn(Seat $seat, TeamTree $tree, ?string $unitId): bool
    {
        return $seat->isOwner() || ($seat->manages() && $tree->covers($seat->unitId(), $unitId));
    }

    public function invite(Seat $seat, ?string $unitId, string $email, Permissions $permissions): Member
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $this->requireUnit($seat, $tree, $unitId);
        if (!$this->buildsIn($seat, $tree, $unitId)) {
            throw new NotTeamLeadException('You cannot invite people into this team');
        }
        $this->requireGrantable($seat, $tree, $unitId, $permissions);

        $email = (new Email($email))->toString();
        $member = $this->members->findByEmail($email);
        if ($member !== null && $member->getAccountId() === $account->getId()) {
            throw new DomainException('This person is already in the team');
        }
        if ($member !== null || $this->users->findByEmail($email) !== null || $this->invites->findByEmail($email) !== null) {
            throw new DomainException('This email already has an account');
        }

        $member = $this->memberFactory->create($account, $unitId, $email, $permissions);
        $this->entityManager->persist($member);
        $this->journal->record($account, $seat->email(), TeamAction::PersonInvited, $unitId, [
            'email' => $email,
            'team' => $this->teamName($tree, $unitId),
            'rights' => implode(', ', $permissions->values()),
        ]);
        $this->entityManager->run();
        $this->sendInvite($seat, $tree, $member);

        return $member;
    }

    public function renew(Seat $seat, string $memberId): Member
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $member = $this->manageableMember($seat, $tree, $memberId);

        $member->renew($this->clock);
        $this->entityManager->persist($member);
        $this->journal->record($account, $seat->email(), TeamAction::InviteRenewed, $member->getUnitId(), [
            'email' => $member->getEmail(),
        ]);
        $this->entityManager->run();
        $this->sendInvite($seat, $tree, $member);

        return $member;
    }

    public function update(Seat $seat, string $memberId, ?string $unitId, Permissions $permissions): Member
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $member = $this->manageableMember($seat, $tree, $memberId);
        $this->requireUnit($seat, $tree, $unitId);
        if (!$this->buildsIn($seat, $tree, $unitId)) {
            throw new NotTeamLeadException('You cannot move people into this team');
        }
        $this->requireGrantable($seat, $tree, $unitId, $permissions);

        $before = $member->getPermissions();
        $from = $member->getUnitId();
        $member->grant($permissions, $this->clock);
        $member->moveTo($unitId, $this->clock);
        $this->entityManager->persist($member);
        $this->journal->record($account, $seat->email(), TeamAction::PersonChanged, $unitId, [
            'email' => $member->getEmail(),
            'from' => $this->teamName($tree, $from),
            'to' => $this->teamName($tree, $unitId),
            'rightsBefore' => implode(', ', $before->values()),
            'rights' => implode(', ', $permissions->values()),
        ]);
        $this->entityManager->run();

        return $member;
    }

    public function remove(Seat $seat, string $memberId): void
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $member = $this->manageableMember($seat, $tree, $memberId);

        $this->entityManager->delete($member);
        $this->journal->record($account, $seat->email(), TeamAction::PersonRemoved, $member->getUnitId(), [
            'email' => $member->getEmail(),
            'team' => $this->teamName($tree, $member->getUnitId()),
        ]);
        $this->entityManager->run();
    }

    public function handOver(Seat $seat, string $memberId, bool $keepFormerOwner): void
    {
        $account = $this->account($seat);
        if (!$seat->isOwner() || $seat->getImpersonator() !== null) {
            throw new NotTeamLeadException('Only the owner hands over the account');
        }
        $member = $this->members->findInAccount($account->getId(), $memberId);
        if ($member === null) {
            throw new MemberMissingException();
        }
        $identityId = $member->getIdentityId();
        $formerIdentity = $account->getIdentityId();
        if ($identityId === null) {
            throw new DomainException('The person has to join before taking over the account');
        }
        if ($formerIdentity === null) {
            throw new DomainException('The owner login is not linked yet');
        }

        $formerEmail = $account->getEmail();
        $newEmail = $member->getEmail();
        $account->handOver($newEmail, $identityId, $this->clock);
        $this->entityManager->persist($account);
        if ($keepFormerOwner) {
            $member->takeOver($formerEmail, $formerIdentity, new Permissions(Permission::cases()), $this->clock);
            $this->entityManager->persist($member);
        } else {
            $this->entityManager->delete($member);
        }
        $this->journal->record($account, $formerEmail, TeamAction::OwnerChanged, null, [
            'from' => $formerEmail,
            'to' => $newEmail,
            'formerOwner' => $keepFormerOwner ? 'stays as a lead' : 'removed',
        ]);
        $this->entityManager->run();
    }

    public function createUnit(Seat $seat, ?string $parentId, string $name): TeamUnit
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $this->requireUnit($seat, $tree, $parentId);
        if (!$this->buildsIn($seat, $tree, $parentId)) {
            throw new NotTeamLeadException('You cannot add teams here');
        }

        $unit = $this->unitFactory->create($account, $parentId, $name);
        $this->requireFreeName($tree, $parentId, $unit->getName(), null);
        $this->entityManager->persist($unit);
        $this->journal->record($account, $seat->email(), TeamAction::TeamCreated, $unit->getId(), [
            'team' => $unit->getName(),
            'inside' => $this->teamName($tree, $parentId),
        ]);
        $this->entityManager->run();

        return $unit;
    }

    public function renameUnit(Seat $seat, string $unitId, string $name): TeamUnit
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $unit = $this->manageableUnit($seat, $tree, $unitId);
        $before = $unit->getName();

        $unit->rename($name, $this->clock);
        $this->requireFreeName($tree, $unit->getParentId(), $unit->getName(), $unit->getId());
        $this->entityManager->persist($unit);
        $this->journal->record($account, $seat->email(), TeamAction::TeamRenamed, $unit->getId(), [
            'from' => $before,
            'to' => $unit->getName(),
        ]);
        $this->entityManager->run();

        return $unit;
    }

    public function moveUnit(Seat $seat, string $unitId, ?string $parentId): TeamUnit
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $unit = $this->manageableUnit($seat, $tree, $unitId);
        $this->requireUnit($seat, $tree, $parentId);
        if (!$this->buildsIn($seat, $tree, $parentId)) {
            throw new NotTeamLeadException('You cannot move teams here');
        }
        if ($tree->covers($unitId, $parentId) && $parentId !== null) {
            throw new InvalidArgumentException('A team cannot move inside itself');
        }
        $from = $unit->getParentId();
        if ($from === $parentId) {
            return $unit;
        }
        $this->requireFreeName($tree, $parentId, $unit->getName(), $unit->getId());

        $this->budget->guard($account, function () use ($account, $tree, $unit, $parentId, $from, $seat): void {
            $this->budget->assertFits(
                $account,
                $parentId,
                fn (): int => $this->subtreeCommitment($account, $tree, $unit),
                $unit->getId(),
            );
            $unit->moveTo($parentId, $this->clock);
            $this->entityManager->persist($unit);
            $this->journal->record($account, $seat->email(), TeamAction::TeamMoved, $unit->getId(), [
                'team' => $unit->getName(),
                'from' => $this->teamName($tree, $from),
                'to' => $this->teamName($tree, $parentId),
            ]);
            $this->entityManager->run();
        });

        return $unit;
    }

    public function removeUnit(Seat $seat, string $unitId): void
    {
        $account = $this->account($seat);
        $tree = $this->units->treeOf($account->getId());
        $unit = $this->manageableUnit($seat, $tree, $unitId);
        if ($tree->children($unitId) !== []) {
            throw new DomainException('Remove or move its sub-teams first');
        }
        if ($this->members->findInUnit($unitId) !== []) {
            throw new DomainException('Move or remove its people first');
        }

        foreach ($this->placements->findInUnit($unitId) as $placement) {
            $placement->moveTo($unit->getParentId(), $this->clock);
            $this->entityManager->persist($placement);
        }
        $this->entityManager->delete($unit);
        $this->journal->record($account, $seat->email(), TeamAction::TeamDeleted, $unit->getParentId(), [
            'team' => $unit->getName(),
            'ordersMovedTo' => $this->teamName($tree, $unit->getParentId()),
        ]);
        $this->entityManager->run();
    }

    public function join(string $identityId, string $email): ?Member
    {
        $identityId = trim($identityId);
        if ($identityId === '') {
            throw new InvalidArgumentException('Identity id is empty');
        }

        $member = $this->members->findByEmail((new Email($email))->toString());
        if ($member === null || ($member->hasJoined() && $member->getIdentityId() !== $identityId)) {
            return null;
        }
        if ($member->hasJoined()) {
            return $member;
        }
        if ($member->isExpired($this->clock->now())) {
            return null;
        }

        $member->join($identityId, $this->clock);
        $this->entityManager->persist($member);
        $account = $this->users->findByPK($member->getAccountId());
        if ($account instanceof User) {
            $this->journal->record($account, $member->getEmail(), TeamAction::PersonJoined, $member->getUnitId(), [
                'email' => $member->getEmail(),
            ]);
        }
        $this->entityManager->run();

        return $member;
    }

    private function account(Seat $seat): User
    {
        $account = $seat->getAccount();
        if ($account->getRole() !== UserRole::Partner) {
            throw new NotTeamLeadException('Teams are part of a partner account');
        }

        return $account;
    }

    private function manageableMember(Seat $seat, TeamTree $tree, string $memberId): Member
    {
        $member = $this->members->findInAccount($seat->getAccount()->getId(), $memberId);
        if ($member === null || !$tree->covers($seat->unitId(), $member->getUnitId())) {
            throw new MemberMissingException();
        }
        if (!$this->managesMember($seat, $tree, $member)) {
            throw new NotTeamLeadException('You cannot change this person');
        }

        return $member;
    }

    private function manageableUnit(Seat $seat, TeamTree $tree, string $unitId): TeamUnit
    {
        $unit = $tree->find($unitId);
        if ($unit === null || !$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }
        if (!$this->managesUnit($seat, $tree, $unitId)) {
            throw new NotTeamLeadException('Only a lead above this team changes it');
        }

        return $unit;
    }

    private function subtreeCommitment(User $account, TeamTree $tree, TeamUnit $unit): int
    {
        $used = $this->budget->usage($account, $tree)[$unit->getId()] ?? ['spentCents' => 0, 'reservedCents' => 0];

        return $used['spentCents'] + $used['reservedCents'];
    }

    private function sendInvite(Seat $seat, TeamTree $tree, Member $member): void
    {
        $account = $seat->getAccount();
        $this->mailer->invite(
            $member,
            $seat->email(),
            $this->links->organizationName($account),
            $this->teamName($tree, $member->getUnitId()),
            $this->links->signUpUrl($account),
        );
    }

    private function teamName(TeamTree $tree, ?string $unitId): string
    {
        $path = array_map(static fn (TeamUnit $unit): string => $unit->getName(), $tree->path($unitId));

        return $path === [] ? 'Whole account' : implode(' › ', $path);
    }

    private function requireUnit(Seat $seat, TeamTree $tree, ?string $unitId): void
    {
        if (!$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }
    }

    private function requireFreeName(TeamTree $tree, ?string $parentId, string $name, ?string $exceptId): void
    {
        foreach ($tree->children($parentId) as $sibling) {
            if ($sibling->getId() !== $exceptId && mb_strtolower($sibling->getName()) === mb_strtolower($name)) {
                throw new DomainException('A team with this name already exists here');
            }
        }
    }

    private function requireGrantable(Seat $seat, TeamTree $tree, ?string $unitId, Permissions $permissions): void
    {
        if ($permissions->isEmpty()) {
            throw new InvalidArgumentException('Pick at least one right');
        }
        if ($seat->isOwner()) {
            return;
        }
        foreach ($permissions->items() as $permission) {
            if (!$seat->can($permission)) {
                throw new AccessDeniedException($permission);
            }
        }
        if ($permissions->allows(Permission::TeamManage) && !$tree->below($seat->unitId(), $unitId)) {
            throw new NotTeamLeadException('A lead appoints leads only in sub-teams below');
        }
    }
}

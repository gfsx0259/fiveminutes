<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\User;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class TeamBudget
{
    private const array THRESHOLDS = [80, 100];

    public function __construct(
        private DatabaseProviderInterface $db,
        private TeamUnitRepository $units,
        private BudgetChangeRepository $changes,
        private BudgetAlertRepository $alerts,
        private MemberRepository $members,
        private TeamJournal $journal,
        private TeamMailer $mailer,
        private UuidFactoryInterface $uuids,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    public function guard(User $account, callable $action): mixed
    {
        $database = $this->db->database();
        $database->execute('SELECT pg_advisory_lock(hashtext(?))', ['team-budget:' . $account->getId()]);
        try {
            return $action();
        } finally {
            $database->execute('SELECT pg_advisory_unlock(hashtext(?))', ['team-budget:' . $account->getId()]);
        }
    }

    /**
     * @return array<string, array{spentCents: int, reservedCents: int}>
     */
    public function usage(User $account, TeamTree $tree): array
    {
        $now = $this->clock->now();
        $month = BudgetPeriod::Month->startsAt($now);
        $week = BudgetPeriod::Week->startsAt($now);

        $direct = [];
        $spent = $this->db->database()->query(
            'SELECT p.unit_id::text AS unit,
                    coalesce(sum(l.price_cents), 0)::bigint AS total,
                    coalesce(sum(l.price_cents) FILTER (WHERE l.sold_at >= ?), 0)::bigint AS month,
                    coalesce(sum(l.price_cents) FILTER (WHERE l.sold_at >= ?), 0)::bigint AS week
             FROM lead_placements l
             JOIN team_orders p ON p.order_id = l.order_id
             WHERE p.account_id = ? AND l.sold_at IS NOT NULL AND p.unit_id IS NOT NULL
             GROUP BY p.unit_id',
            [$month, $week, $account->getId()],
        )->fetchAll();
        foreach ($spent as $row) {
            $direct[(string) $row['unit']] = [
                BudgetPeriod::Total->value => (int) $row['total'],
                BudgetPeriod::Month->value => (int) $row['month'],
                BudgetPeriod::Week->value => (int) $row['week'],
                'reserved' => 0,
            ];
        }

        $reserved = $this->db->database()->query(
            "SELECT p.unit_id::text AS unit, coalesce(sum(greatest(0, o.volume - o.supplied_count) * o.price_cents), 0)::bigint AS cents
             FROM orders o
             JOIN team_orders p ON p.order_id = o.id
             WHERE p.account_id = ? AND o.stock = false AND o.status <> 'finished' AND p.unit_id IS NOT NULL
             GROUP BY p.unit_id",
            [$account->getId()],
        )->fetchAll();
        foreach ($reserved as $row) {
            $direct[(string) $row['unit']]['reserved'] = (int) $row['cents'];
        }

        $usage = [];
        foreach ($tree->within(null) as $unit) {
            $period = $unit->getBudgetPeriod()->value;
            $total = ['spentCents' => 0, 'reservedCents' => 0];
            foreach ($tree->within($unit->getId()) as $inner) {
                $total['spentCents'] += $direct[$inner->getId()][$period] ?? 0;
                $total['reservedCents'] += $direct[$inner->getId()]['reserved'] ?? 0;
            }
            $usage[$unit->getId()] = $total;
        }

        return $usage;
    }

    public function orderCommitmentCents(string $orderId): int
    {
        $row = $this->db->database()->query(
            "SELECT coalesce((SELECT sum(price_cents) FROM lead_placements WHERE order_id = o.id AND sold_at IS NOT NULL), 0)
                  + CASE WHEN o.stock = false AND o.status <> 'finished' THEN greatest(0, o.volume - o.supplied_count) * o.price_cents ELSE 0 END
                    AS cents
             FROM orders o
             WHERE o.id = ?",
            [$orderId],
        )->fetch();

        return (int) ($row['cents'] ?? 0);
    }

    /**
     * @param callable(): int $costCents
     */
    public function assertFits(User $account, ?string $unitId, callable $costCents, ?string $from = null): void
    {
        $tree = $this->units->treeOf($account->getId());
        $limited = array_values(array_filter(
            $tree->path($unitId),
            static fn (TeamUnit $unit): bool => $unit->getBudgetCents() !== null && !$tree->covers($unit->getId(), $from),
        ));
        if ($limited === []) {
            return;
        }

        $cost = $costCents();
        $usage = $this->usage($account, $tree);
        foreach ($limited as $unit) {
            $left = $this->leftCents($unit, $usage);
            if ($cost > $left) {
                throw new BudgetExceededException($unit, $left);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nearest(Seat $seat): ?array
    {
        $account = $seat->getAccount();
        $tree = $this->units->treeOf($account->getId());
        $limited = array_values(array_filter(
            array_reverse($tree->path($seat->unitId())),
            static fn (TeamUnit $unit): bool => $unit->getBudgetCents() !== null,
        ));
        $unit = $limited[0] ?? null;
        if ($unit === null) {
            return null;
        }

        return $this->present($unit, $this->usage($account, $tree)[$unit->getId()] ?? ['spentCents' => 0, 'reservedCents' => 0]);
    }

    /**
     * @param array{spentCents: int, reservedCents: int} $usage
     * @return array<string, mixed>
     */
    public function present(TeamUnit $unit, array $usage): array
    {
        $budget = $unit->getBudgetCents();
        $now = $this->clock->now();

        return [
            'teamId' => $unit->getId(),
            'teamName' => $unit->getName(),
            'budgetCents' => $budget,
            'period' => $unit->getBudgetPeriod()->value,
            'resetsAt' => $unit->getBudgetPeriod()->resetsAt($now)?->format(DATE_ATOM),
            'spentCents' => $usage['spentCents'],
            'reservedCents' => $usage['reservedCents'],
            'leftCents' => $budget === null ? null : $budget - $usage['spentCents'] - $usage['reservedCents'],
            'usedPercent' => $budget === null ? null : $this->percent($usage, $budget),
        ];
    }

    public function limit(Seat $seat, string $unitId, ?int $budgetCents, BudgetPeriod $period): TeamUnit
    {
        $account = $seat->getAccount();
        $tree = $this->units->treeOf($account->getId());
        $unit = $tree->find($unitId);
        if ($unit === null || !$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }
        if (!$seat->isOwner() && !($seat->manages() && $tree->below($seat->unitId(), $unitId))) {
            throw new NotTeamLeadException('Only a lead above this team hands out its budget');
        }
        if ($unit->getBudgetCents() === $budgetCents && $unit->getBudgetPeriod() === $period) {
            return $unit;
        }

        $unit->limitBudget($budgetCents, $period, $this->clock);
        $this->entityManager->persist($unit);
        $this->entityManager->persist(new BudgetChange(
            $this->uuids->uuid4()->toString(),
            $unit->getId(),
            $budgetCents,
            $period->value,
            $seat->email(),
            $this->clock->now(),
        ));
        $this->journal->record($account, $seat->email(), TeamAction::BudgetChanged, $unit->getId(), [
            'team' => $unit->getName(),
            'budgetCents' => $budgetCents,
            'period' => $period->value,
        ]);
        $this->entityManager->run();

        return $unit;
    }

    /**
     * @return list<BudgetChange>
     */
    public function history(Seat $seat, string $unitId): array
    {
        $tree = $this->units->treeOf($seat->getAccount()->getId());
        if (!$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }

        return $this->changes->latestFor($unitId);
    }

    public function alert(User $account): int
    {
        $tree = $this->units->treeOf($account->getId());
        $limited = array_values(array_filter(
            $tree->within(null),
            static fn (TeamUnit $unit): bool => $unit->getBudgetCents() !== null,
        ));
        if ($limited === []) {
            return 0;
        }

        $now = $this->clock->now();
        $usage = $this->usage($account, $tree);
        $sent = 0;
        foreach ($limited as $unit) {
            $used = $usage[$unit->getId()] ?? ['spentCents' => 0, 'reservedCents' => 0];
            $percent = $this->percent($used, (int) $unit->getBudgetCents());
            $periodStart = $unit->getBudgetPeriod()->startsAt($now) ?? new DateTimeImmutable('1970-01-01 00:00:00 UTC');
            foreach (self::THRESHOLDS as $threshold) {
                if ($percent < $threshold || $this->alerts->sent($unit->getId(), $periodStart, $threshold)) {
                    continue;
                }
                $this->entityManager->persist(new BudgetAlert(
                    $this->uuids->uuid4()->toString(),
                    $unit->getId(),
                    $periodStart,
                    $threshold,
                    $now,
                ));
                $this->journal->record($account, 'Rider', TeamAction::BudgetAlert, $unit->getId(), [
                    'team' => $unit->getName(),
                    'threshold' => $threshold,
                    'usedCents' => $used['spentCents'] + $used['reservedCents'],
                    'budgetCents' => $unit->getBudgetCents(),
                ]);
                $this->entityManager->run();
                $this->mailer->budgetAlert($this->leadsOf($account, $tree, $unit), $unit, $threshold, $used['spentCents'] + $used['reservedCents'], $now);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return list<string>
     */
    private function leadsOf(User $account, TeamTree $tree, TeamUnit $unit): array
    {
        $emails = [$account->getEmail()];
        foreach ($this->members->findByAccount($account->getId()) as $member) {
            if ($member->hasJoined() && $member->getPermissions()->allows(Permission::TeamManage) && $tree->covers($member->getUnitId(), $unit->getId())) {
                $emails[] = $member->getEmail();
            }
        }

        return $emails;
    }

    /**
     * @param array{spentCents: int, reservedCents: int} $usage
     */
    private function percent(array $usage, int $budgetCents): int
    {
        $used = $usage['spentCents'] + $usage['reservedCents'];

        return $budgetCents > 0 ? (int) floor($used * 100 / $budgetCents) : ($used > 0 ? 100 : 0);
    }

    /**
     * @param array<string, array{spentCents: int, reservedCents: int}> $usage
     */
    private function leftCents(TeamUnit $unit, array $usage): int
    {
        $used = $usage[$unit->getId()] ?? ['spentCents' => 0, 'reservedCents' => 0];

        return (int) $unit->getBudgetCents() - $used['spentCents'] - $used['reservedCents'];
    }
}

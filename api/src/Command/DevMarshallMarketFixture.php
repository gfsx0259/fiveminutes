<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadStatus;
use App\Context\Lead\Sale;
use App\Context\Market\Order;
use App\Context\Market\Preset;
use App\Context\Market\PresetDesk;
use App\Context\Market\PresetRepository;
use App\Context\Market\OrderDesk;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use App\Context\Wallet\WalletDesk;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Ramsey\Uuid\UuidFactoryInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class DevMarshallMarketFixture
{
    private const string APEX = 'marshall.io';
    private const string ADMIN_EMAIL = 'admin@marshall.io';
    private const string PARTNER_EMAIL = 'partner@marshall.io';
    private const string BUYER_EMAIL = 'buyer@marshall.io';

    public function __construct(
        private UserRepository $users,
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private EntityManagerInterface $entityManager,
        private WalletDesk $wallet,
        private PresetDesk $presetDesk,
        private PresetRepository $presets,
        private OrderDesk $orderDesk,
        private Sale $sale,
    ) {}

    public function seed(OutputInterface $output): void
    {
        $admin = $this->ensureAdmin($output);
        $partner = $this->requireUser(self::PARTNER_EMAIL);
        $buyer = $this->requireUser(self::BUYER_EMAIL);

        $this->wallet->deposit($partner, 50_000_000);
        $this->wallet->deposit($buyer, 10_000_000);
        $output->writeln('wallet funded partner + buyer');

        $partner = $this->requireUser(self::PARTNER_EMAIL);
        $presets = $this->resolvePresets($admin, $partner, $output);
        $output->writeln('presets ' . count($presets));

        $today = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $liveOrders = $this->openLiveOrders($partner, $presets, $today, $output);
        $stockPoolPresets = array_slice($presets, 0, 6);
        $this->feedLeads($buyer, $liveOrders, $stockPoolPresets, $output);
        $this->openStockOrders($partner, $presets, $stockPoolPresets, $output);

        $output->writeln('done');
    }

    private function ensureAdmin(OutputInterface $output): User
    {
        $admin = $this->users->findByEmail(self::ADMIN_EMAIL);
        if ($admin instanceof User) {
            return $admin;
        }

        $partner = $this->requireUser(self::PARTNER_EMAIL);
        $now = $this->clock->now();
        $admin = new User(
            $this->uuids->uuid4()->toString(),
            $partner->getOrganizationId(),
            self::ADMIN_EMAIL,
            UserRole::Admin,
            null,
            [],
            0,
            $now,
            $now,
        );
        $this->entityManager->persist($admin);
        $this->entityManager->run();
        $output->writeln('created ' . self::ADMIN_EMAIL);

        return $admin;
    }

    private function requireUser(string $email): User
    {
        $user = $this->users->findByEmail($email);
        if (!$user instanceof User) {
            throw new DomainException('User ' . $email . ' is missing on the stage');
        }

        return $user;
    }

    /**
     * @return list<Preset>
     */
    private function resolvePresets(User $admin, User $partner, OutputInterface $output): array
    {
        $existing = [];
        foreach ($this->presets->findByOrganization($partner->getOrganizationId()) as $preset) {
            $existing[] = $preset;
        }
        if ($existing !== []) {
            $output->writeln('reuse ' . count($existing) . ' existing presets');

            return $existing;
        }

        return $this->openPresets($admin, $output);
    }

    /**
     * @return list<Preset>
     */
    private function openPresets(User $admin, OutputInterface $output): array
    {
        $countries = ['DE', 'US', 'GB', 'FR', 'IT', 'ES', 'PL', 'CA', 'AU', 'NL'];
        $sources = ['facebook', 'google', 'tiktok'];
        $verticals = ['gambling', 'betting', 'crypto', 'forex', 'dating', 'nutra', 'finance', 'other'];
        $genders = ['m', 'f', 'all'];
        $ages = ['25-34', '35-44', '18-24', '45-54', ''];
        $models = ['cpl', 'cpa'];

        $presets = [];
        $n = 0;
        foreach ($countries as $country) {
            foreach ($sources as $source) {
                foreach ($verticals as $vertical) {
                    if ($n >= 40) {
                        break 3;
                    }
                    $gender = $genders[$n % count($genders)];
                    $age = $ages[$n % count($ages)];
                    $model = $models[$n % count($models)];
                    $cr = 5 + ($n % 20);
                    $base = (40 + ($n % 12) * 5) * 100;
                    try {
                        $preset = $this->presetDesk->open(
                            $admin,
                            $country,
                            $source,
                            $age,
                            $gender,
                            $vertical,
                            $model,
                            $cr,
                            $base,
                        );
                        $presets[] = $preset;
                        ++$n;
                    } catch (\Throwable $exception) {
                        $output->writeln('skip preset: ' . $exception->getMessage());
                    }
                }
            }
        }

        return $presets;
    }

    /**
     * @param list<Preset> $presets
     * @return list<array{preset: Preset, order: Order, fill: int}>
     */
    private function openLiveOrders(User $partner, array $presets, DateTimeImmutable $today, OutputInterface $output): array
    {
        $rows = [];
        $tomorrow = $today->modify('+1 day');
        foreach ($presets as $index => $preset) {
            if ($index >= 28) {
                break;
            }
            $serviceOn = ($index % 5 === 0) ? $tomorrow : $today;
            $volume = 30 + ($index % 7) * 20;
            $price = $preset->getBasePriceCents() + ($index % 3) * 100;
            try {
                $order = $this->orderDesk->open(
                    $partner,
                    $preset->getId(),
                    $preset->getCountry(),
                    $volume,
                    $price,
                    $preset->getPaymentModel(),
                    false,
                    $serviceOn,
                    '09:00',
                    '21:00',
                );
                $fill = $index % 4 === 3 ? 0 : min(12, (int) floor($volume * (0.2 + ($index % 4) * 0.15)));
                $rows[] = ['preset' => $preset, 'order' => $order, 'fill' => $fill];
            } catch (\Throwable $exception) {
                $output->writeln('skip order: ' . $exception->getMessage());
            }
        }
        $output->writeln('live orders ' . count($rows));

        return $rows;
    }

    /**
     * @param list<array{preset: Preset, order: Order, fill: int}> $liveOrders
     * @param list<Preset> $stockPoolPresets
     */
    private function feedLeads(User $buyer, array $liveOrders, array $stockPoolPresets, OutputInterface $output): void
    {
        $sold = 0;
        $stocked = 0;
        $now = $this->clock->now();
        foreach ($liveOrders as $row) {
            $fill = $row['fill'];
            for ($i = 0; $i < $fill; ++$i) {
                $lead = $this->syntheticLead($buyer, $row['preset'], $i, $now);
                $this->sale->place($lead);
                ++$sold;
            }
        }
        foreach ($stockPoolPresets as $presetIndex => $preset) {
            $count = 15 + $presetIndex * 5;
            for ($i = 0; $i < $count; ++$i) {
                $lead = $this->syntheticLead($buyer, $preset, 1000 + $i, $now);
                $this->sale->place($lead);
                ++$stocked;
            }
        }
        $output->writeln("leads sold ~{$sold}, extra stock feed ~{$stocked}");
    }

    /**
     * @param list<Preset> $presets
     * @param list<Preset> $stockPoolPresets
     */
    private function openStockOrders(User $partner, array $presets, array $stockPoolPresets, OutputInterface $output): void
    {
        $opened = 0;
        foreach ($stockPoolPresets as $index => $preset) {
            $volume = 5 + $index * 2;
            try {
                $this->orderDesk->open(
                    $partner,
                    $preset->getId(),
                    $preset->getCountry(),
                    $volume,
                    $preset->getBasePriceCents(),
                    $preset->getPaymentModel(),
                    true,
                    null,
                    '00:00',
                    '23:59',
                );
                ++$opened;
            } catch (\Throwable $exception) {
                $output->writeln('skip stock order: ' . $exception->getMessage());
            }
        }
        foreach (array_slice($presets, 28, 6) as $preset) {
            try {
                $this->orderDesk->open(
                    $partner,
                    $preset->getId(),
                    $preset->getCountry(),
                    50,
                    $preset->getBasePriceCents(),
                    $preset->getPaymentModel(),
                    false,
                    $this->clock->now()->setTimezone(new DateTimeZone('UTC')),
                    '08:00',
                    '20:00',
                );
                ++$opened;
            } catch (\Throwable) {
            }
        }
        $output->writeln('extra orders ' . $opened);
    }

    private function syntheticLead(User $buyer, Preset $preset, int $seq, DateTimeImmutable $now): Lead
    {
        $gender = $preset->getGender() === 'all'
            ? ($seq % 2 === 0 ? 'm' : 'f')
            : $preset->getGender();
        $age = $preset->getAge() !== '' ? $this->ageInside($preset->getAge()) : '30-35';
        $uuid = $this->uuids->uuid4()->toString();

        return new Lead(
            $uuid,
            $buyer->getOrganizationId(),
            $buyer->getId(),
            null,
            null,
            'lead' . $seq . '@stage.marshall.io',
            '+1555' . str_pad((string) (1000000 + $seq), 7, '0', STR_PAD_LEFT),
            'Stage',
            'Lead' . $seq,
            $preset->getCountry(),
            $preset->getTrafficSource(),
            $age,
            $gender,
            '198.51.100.' . (($seq % 250) + 1),
            'aff-' . $preset->getVertical(),
            'uniq-' . $uuid,
            LeadStatus::New,
            0,
            $now,
            $now,
        );
    }

    private function ageInside(string $bracket): string
    {
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($bracket), $m) === 1) {
            $mid = (int) floor(((int) $m[1] + (int) $m[2]) / 2);

            return $mid . '-' . ($mid + 1);
        }

        return '30-35';
    }
}
<?php

declare(strict_types=1);

namespace App\Context\Team;

use InvalidArgumentException;

final readonly class Permissions
{
    /** @var list<Permission> */
    private array $items;

    /**
     * @param iterable<Permission|string> $items
     */
    public function __construct(iterable $items = [])
    {
        $wanted = [];
        foreach ($items as $item) {
            $permission = $item instanceof Permission ? $item : Permission::tryFrom((string) $item);
            if ($permission === null) {
                throw new InvalidArgumentException('Unknown permission: ' . (string) $item);
            }
            $wanted[$permission->value] = true;
        }

        $this->items = array_values(array_filter(
            Permission::cases(),
            static fn (Permission $permission): bool => isset($wanted[$permission->value]),
        ));
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->items, true);
    }

    /**
     * @return list<Permission>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function equals(self $other): bool
    {
        return $this->values() === $other->values();
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(static fn (Permission $permission): string => $permission->value, $this->items);
    }
}

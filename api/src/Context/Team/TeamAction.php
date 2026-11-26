<?php

declare(strict_types=1);

namespace App\Context\Team;

enum TeamAction: string
{
    case TeamCreated = 'team.created';
    case TeamRenamed = 'team.renamed';
    case TeamMoved = 'team.moved';
    case TeamDeleted = 'team.deleted';
    case PersonInvited = 'person.invited';
    case InviteRenewed = 'person.reinvited';
    case PersonJoined = 'person.joined';
    case PersonChanged = 'person.changed';
    case PersonRemoved = 'person.removed';
    case OwnerChanged = 'owner.changed';
    case BudgetChanged = 'budget.changed';
    case BudgetAlert = 'budget.alert';
    case OrderOpened = 'order.opened';
    case OrderMoved = 'order.moved';
    case DataExported = 'data.exported';

    public function label(): string
    {
        return match ($this) {
            self::TeamCreated => 'Created a team',
            self::TeamRenamed => 'Renamed a team',
            self::TeamMoved => 'Moved a team',
            self::TeamDeleted => 'Deleted a team',
            self::PersonInvited => 'Invited a person',
            self::InviteRenewed => 'Renewed an invite',
            self::PersonJoined => 'Joined the team',
            self::PersonChanged => 'Changed access',
            self::PersonRemoved => 'Removed a person',
            self::OwnerChanged => 'Handed over the account',
            self::BudgetChanged => 'Changed a budget',
            self::BudgetAlert => 'Budget alert',
            self::OrderOpened => 'Opened an order',
            self::OrderMoved => 'Moved an order',
            self::DataExported => 'Exported data',
        };
    }
}

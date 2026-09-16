<?php

namespace App\Enums;

/**
 * Where a case stands with the DSNO.
 */
enum CaseNotificationStatus: string
{
    /** An immediately notifiable case the DSNO has not yet been told about. */
    case Pending = 'pending';

    /** The DSNO has been notified. */
    case Notified = 'notified';

    /** A weekly-reportable case: goes on the IDSR 002 return, no individual notification. */
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting notification',
            self::Notified => 'DSNO notified',
            self::Weekly => 'Weekly return',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'red',
            self::Notified => 'green',
            self::Weekly => 'muted',
        };
    }
}

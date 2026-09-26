<?php

declare(strict_types=1);

final class Event
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
    }
}

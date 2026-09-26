<?php

declare(strict_types=1);

final class Member
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM participantes')->fetchColumn();
    }
}

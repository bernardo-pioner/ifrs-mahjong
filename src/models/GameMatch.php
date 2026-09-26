<?php

declare(strict_types=1);

final class GameMatch
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM partidas')->fetchColumn();
    }
}

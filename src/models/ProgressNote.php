<?php

declare(strict_types=1);

final class ProgressNote
{
    public static function count(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM rankings')->fetchColumn();
    }
}

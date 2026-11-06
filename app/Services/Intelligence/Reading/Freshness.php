<?php

namespace App\Services\Intelligence\Reading;

/** Whether a figure can honestly be called current. Never decided by colour or by the reader's guess. */
enum Freshness: string
{
    case Current = 'current';
    case Stale = 'stale';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::Stale => 'Out of date',
            self::Missing => 'Not available',
        };
    }
}

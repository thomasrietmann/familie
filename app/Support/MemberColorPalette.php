<?php

namespace App\Support;

class MemberColorPalette
{
    public const DEFAULT = 'sky';

    public const COLORS = [
        'sky' => '#0284c7',
        'teal' => '#0f766e',
        'emerald' => '#059669',
        'lime' => '#65a30d',
        'amber' => '#d97706',
        'orange' => '#ea580c',
        'rose' => '#e11d48',
        'pink' => '#db2777',
        'fuchsia' => '#c026d3',
        'purple' => '#7c3aed',
        'violet' => '#6d28d9',
        'indigo' => '#4f46e5',
        'blue' => '#2563eb',
        'cyan' => '#0891b2',
        'mint' => '#10b981',
        'green' => '#16a34a',
        'yellow' => '#ca8a04',
        'red' => '#dc2626',
        'slate' => '#475569',
        'stone' => '#78716c',
    ];

    public static function keys(): array
    {
        return array_keys(self::COLORS);
    }

    public static function options(): array
    {
        return self::COLORS;
    }

    public static function hex(?string $key): string
    {
        return self::COLORS[$key ?? self::DEFAULT] ?? self::COLORS[self::DEFAULT];
    }
}

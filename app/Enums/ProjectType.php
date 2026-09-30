<?php

namespace App\Enums;

enum ProjectType: string
{
    case Goods = 'goods';
    case Infrastructure = 'infrastructure';
    case Consulting = 'consulting';

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'Goods',
            self::Infrastructure => 'Infrastructure',
            self::Consulting => 'Consulting Services',
        };
    }
}

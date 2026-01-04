<?php

namespace App\Enum;

use MyCLabs\Enum\Enum;

/**
 * @method static Low()
 * @method static Peak()
 */
final class TariffSeasonType extends Enum
{
    private const Low  = 'low';
    private const Peak = 'peak';
}
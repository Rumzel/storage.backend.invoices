<?php

namespace App\Enum;

use MyCLabs\Enum\Enum;

/**
 * @method static Standard()
 * @method static Oversize()
 */
final class TariffType extends Enum
{
    private const Standard  = 'Standard';
    private const Oversize = 'Oversize';
}
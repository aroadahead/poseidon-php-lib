<?php

declare(strict_types=1);

namespace Poseidon;

use AthenaCore\Mvc\Application\Application\Core\ApplicationCore;

final class Poseidon
{
    private static ApplicationCore $applicationCore;

    public static function setCore(ApplicationCore $applicationCore):void
    {
        self::$applicationCore = $applicationCore;
    }

    public static function getCore():ApplicationCore
    {
        return self::$applicationCore;
    }
}
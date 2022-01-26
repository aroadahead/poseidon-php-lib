<?php

declare(strict_types=1);

namespace Poseidon;

use AthenaCore\Mvc\Application\Application\Core\ApplicationCore;
use Poseidon\Registry\Registry;

final class Poseidon
{
    private static ApplicationCore $applicationCore;
    private static ?Registry $registry=null;

    public static function setCore(ApplicationCore $applicationCore):void
    {
        self::$applicationCore = $applicationCore;
    }

    public static function getCore():ApplicationCore
    {
        return self::$applicationCore;
    }

    public static function registry():Registry
    {
        if(self::$registry===null){
            self::$registry = Registry::getInstance();
        }
        return self::$registry;
    }
}
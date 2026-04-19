<?php

declare(strict_types=1);

namespace Konayuki\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Konayuki\IdGenerator;

/**
 * @method static \Konayuki\SnowflakeId next()
 */
final class Konayuki extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdGenerator::class;
    }
}

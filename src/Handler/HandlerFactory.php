<?php
/**
 * Laika Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session\Handler;

use Laika\Model\Model;
use Laika\Session\SessionConfig;
use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Builds the session driver named by SessionConfig.
 */
class HandlerFactory
{
    /**
     * @param string $driver One of SessionConfig::DRIVERS
     * @param array<string,mixed> $params Driver params
     * @return SessionDriverInterface
     */
    public static function make(string $driver, array $params = []): SessionDriverInterface
    {
        return match ($driver) {
            SessionConfig::DRIVER_FILE      => new FileHandler($params),
            SessionConfig::DRIVER_REDIS     => new RedisHandler($params),
            SessionConfig::DRIVER_MEMCACHED => new MemcachedHandler($params),
            SessionConfig::DRIVER_MYSQL     => new MySQLHandler($params),
            SessionConfig::DRIVER_MODEL     => static::model($params),
            default                         => throw new SessionHandlerException(
                "Unknown session driver [{$driver}]. Expected one of: " . implode(', ', SessionConfig::DRIVERS) . '.'
            ),
        };
    }

    /**
     * The model driver is the one that can be unavailable at runtime, because
     * laika-model is an optional dependency. Fail with the package name rather
     * than a bare "class not found".
     *
     * @param array<string,mixed> $params
     * @return SessionDriverInterface
     */
    protected static function model(array $params): SessionDriverInterface
    {
        if (!class_exists(Model::class)) {
            throw new SessionHandlerException(
                'The [model] session driver needs laikait/laika-model. Install it, or use SessionConfig::mysql() '
                . 'which talks to PDO directly.'
            );
        }

        return new ModelHandler($params);
    }
}

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

namespace Laika\Session;

use PDO;
use Redis;
use Memcached;
use Laika\Model\Model;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Session Configuration
 *
 * The single entry point for configuring the session. Pick exactly one driver
 * by calling its method; calling a second driver method switches drivers.
 *
 *   SessionConfig::file(['path' => '/var/sessions', 'prefix' => 'LK']);
 *   SessionConfig::redis($redis, ['prefix' => 'LK']);
 *   SessionConfig::memcached($memcached, ['prefix' => 'LK']);
 *   SessionConfig::mysql($pdo, ['table' => 'sessions']);
 *   SessionConfig::model(['connection' => 'default']);
 *
 *   SessionConfig::options(['name' => 'LFSESS']);
 *   SessionConfig::cookies(['secure' => true]);
 *
 * The clients are taken pre-connected and pre-authenticated, so this package
 * never handles credentials.
 */
class SessionConfig
{
    public const DRIVER_FILE      = 'file';
    public const DRIVER_REDIS     = 'redis';
    public const DRIVER_MEMCACHED = 'memcached';
    public const DRIVER_MYSQL     = 'mysql';
    public const DRIVER_MODEL     = 'model';

    /** @var string[] Every driver this package can build. */
    public const DRIVERS = [
        self::DRIVER_FILE,
        self::DRIVER_REDIS,
        self::DRIVER_MEMCACHED,
        self::DRIVER_MYSQL,
        self::DRIVER_MODEL,
    ];

    /** @var ?string $driver Selected driver name, null until a driver method is called. */
    protected static ?string $driver = null;

    /** @var array<string,mixed> $params Params for the selected driver. */
    protected static array $params = [];

    /** @var array<string,mixed> $options Session start options, merged over the defaults. */
    protected static array $options = [];

    /** @var array<string,mixed> $cookies Session cookie params, merged over the defaults. */
    protected static array $cookies = [];

    ########################################################################
    /*----------------------------- DRIVERS ------------------------------*/
    ########################################################################

    /**
     * File Driver
     * @param array<string,mixed> $params Example: ['path' => '/var/sessions', 'prefix' => 'LK']
     * Defaults: path = session_save_path() (or the system temp dir), prefix = 'LK'
     * @return void
     */
    public static function file(array $params = []): void
    {
        static::select(self::DRIVER_FILE, array_merge(['prefix' => 'LK'], $params));
    }

    /**
     * Redis Driver
     * @param Redis $client A connected and authenticated client.
     * @param array<string,mixed> $params Example: ['prefix' => 'LK', 'lifetime' => 1440]
     * @return void
     */
    public static function redis(Redis $client, array $params = []): void
    {
        static::select(self::DRIVER_REDIS, array_merge(['prefix' => 'LK'], $params, ['client' => $client]));
    }

    /**
     * Memcached Driver
     * @param Memcached $client A configured client with at least one server added.
     * @param array<string,mixed> $params Example: ['prefix' => 'LK', 'lifetime' => 1440]
     * @return void
     */
    public static function memcached(Memcached $client, array $params = []): void
    {
        static::select(self::DRIVER_MEMCACHED, array_merge(['prefix' => 'LK'], $params, ['client' => $client]));
    }

    /**
     * MySQL Driver. Raw PDO, no Laika Model required.
     * @param PDO $pdo A connected instance.
     * @param array<string,mixed> $params Example: ['table' => 'sessions', 'install' => false]
     * Set install to true to create the table on first use. Leave it false in
     * production, where the runtime user should not hold DDL privileges.
     * @return void
     */
    public static function mysql(PDO $pdo, array $params = []): void
    {
        static::select(self::DRIVER_MYSQL, array_merge(
            ['table' => 'sessions', 'install' => false],
            $params,
            ['pdo' => $pdo]
        ));
    }

    /**
     * Laika Model Driver. Requires laikait/laika-model.
     * @param array<string,mixed> $params Example: ['connection' => 'default', 'install' => false]
     * @return void
     */
    public static function model(array $params = []): void
    {
        if (!class_exists(Model::class)) {
            throw new SessionHandlerException(
                'The [model] session driver needs laikait/laika-model. Install it, or use SessionConfig::mysql() '
                . 'which talks to PDO directly.'
            );
        }

        static::select(self::DRIVER_MODEL, array_merge(['connection' => 'default', 'install' => false], $params));
    }

    ########################################################################
    /*------------------------- SHARED SETTINGS --------------------------*/
    ########################################################################

    /**
     * Session Start Options. Merges over the defaults, so a partial call leaves the rest intact.
     * @param array<string,mixed> $options Example: ['name' => 'LFSESS', 'gc_maxlifetime' => 1440]
     * @return array<string,mixed> The full merged set.
     */
    public static function options(array $options = []): array
    {
        static::$options = array_merge(static::$options, $options);
        return array_merge(static::defaultOptions(), static::$options);
    }

    /**
     * Session Cookie Params. Merges over the defaults, so a partial call leaves the rest intact.
     * @param array<string,mixed> $cookies Example: ['path' => '/', 'samesite' => 'Strict']
     * @return array<string,mixed> The full merged set.
     */
    public static function cookies(array $cookies = []): array
    {
        static::$cookies = array_merge(static::$cookies, $cookies);
        return array_merge(static::defaultCookies(), static::$cookies);
    }

    ########################################################################
    /*------------------------------ STATE -------------------------------*/
    ########################################################################

    /**
     * Selected driver name, or null when no driver method has been called.
     * @return ?string
     */
    public static function driver(): ?string
    {
        return static::$driver;
    }

    /**
     * Params for the selected driver.
     * @return array<string,mixed>
     */
    public static function params(): array
    {
        return static::$params;
    }

    /**
     * Whether a driver has been selected.
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return static::$driver !== null;
    }

    /**
     * Clear everything. Intended for tests, which need to rebuild static state between cases.
     * @return void
     */
    public static function reset(): void
    {
        static::$driver  = null;
        static::$params  = [];
        static::$options = [];
        static::$cookies = [];
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * Select a driver and replace its params.
     * @param string $driver
     * @param array<string,mixed> $params
     * @return void
     */
    protected static function select(string $driver, array $params): void
    {
        static::$driver = $driver;
        static::$params = $params;
    }

    /**
     * @return array<string,mixed> Default Session Options
     */
    protected static function defaultOptions(): array
    {
        return [
            'name'              =>  'LFSESS',
            'use_only_cookies'  =>  true,
            'use_strict_mode'   =>  true,
            'gc_probability'    =>  1,
            'gc_divisor'        =>  100,
            'gc_maxlifetime'    =>  1440,
        ];
    }

    /**
     * @return array<string,mixed> Default Session Cookies
     *
     * secure follows the connection rather than being hardcoded true. Forcing it
     * on plain HTTP means the browser never returns the cookie, so every request
     * gets a fresh session and the failure is completely silent.
     */
    protected static function defaultCookies(): array
    {
        return [
            'path'      =>  '/',
            'secure'    =>  static::isSecureConnection(),
            'httponly'  =>  true,
            'samesite'  =>  'Strict',
        ];
    }

    /**
     * @return bool Whether the current request arrived over TLS.
     */
    protected static function isSecureConnection(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
    }
}

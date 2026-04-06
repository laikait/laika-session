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

class Session
{
    /** @var string $for Session For */
    protected string $for = 'APP';

    /**
     * Set Session For
     * @param ?string $for
     * @return string
     */
    public static function for(?string $for = null): string
    {
        if ($for !== null) self::$for = strtoupper(trim($for));
        return self::$for;

    }

    /**
     * Session Config
     * @param null|PDO|Redis|Memcached $instance
     * @param array $args Example: ['path' => '/session_path/', 'prefix' => 'LK']
     * File Session: Ignore This Parameter.
     * PDO Session: PDO Object or ['driver'=>'pdo'] and dsn,username,password Keys are Required
     * Redis Session: Redis Object or ['driver'=>'redis']. host,port,timeout,prefix,password Keys are Optional
     * Memcached Session: Memcached Object or ['driver'=>'memcached']. host,port,timeout,prefix Keys are Optional
     * @return void
     */
    public static function config(null|PDO|Redis|Memcached $instance = null, array $args = ['prefix' => 'LK']): void
    {
        SessionManager::config($instance, $args);
    }

    /**
     * Set Session Options
     * @param array $options. Example ['name'=>'PHPSESSID'] and any other session options
     * @return void
     */
    public static function setOptions(array $options): void
    {
        SessionManager::setOptions($options);
    }

    /**
     * Set Session Cookies
     * @param array $options. Example ['name'=>'PHPSESSID'] and any other session options
     * @return void
     */
    public static function setCookies(array $cookies): void
    {
        SessionManager::setCookies($cookies);
    }

    /**
     * Set Session Key & Values
     * @param string|array $name Required Argument as key name or array with key & value
     * @param mixed $value Optional Argument. If $name is string this Param is Required.
     * @return void
     */
    public static function set(string|array $name, mixed $value = null): void
    {
        SessionManager::start();
        if (is_string($name)) {
            $name = [$name => $value];
        }

        foreach ($name as $k => $v) {
            $_SESSION[self::$for][$k] = $v;
        }
    }

    /**
     * Get Session Value From Key
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Get Data From $_SESSION[$for][$key].
     * @param mixed $default Default Value To Return. Default is null
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        SessionManager::start();
        return $_SESSION[self::$for][$key] ?? $default;
    }

    /**
     * Check Session Key Exist
     * @param string $key Required Argument
     * @param ?string $for Optional Argument. It Will Check Data Like $_SESSION[$for][$key].
     * @return bool
     */
    public static function has(string $key, ?string $for = null): bool
    {
        SessionManager::start();
        $for = $for ? strtoupper($for) : self::$for;
        return isset($_SESSION[$for][$key]);
    }

    /**
     * Remove Session Key if Exist
     * @param string $key Required Argument
     * @param ?string $for Optional Argument. It Will Remove Data If $_SESSION[$for][$key] Exist.
     * @return void
     */
    public static function pop(string $key, ?string $for = null): void
    {
        $for = $for ? strtoupper($for) : self::$for;
        if (self::has($key, $for)) {
            unset($_SESSION[$for][$key]);
        }
    }

    /**
     * Get All Session Key & Values
     * @return array
     */
    public static function all(): array
    {
        SessionManager::start();
        return $_SESSION;
    }

    /**
     * Regenerate Session ID
     * @param bool $deleteOldData Optional Argument. Default is true
     * @return bool
     */
    public static function regenerate(bool $deleteOldData = true): bool
    {
        SessionManager::start();
        return session_regenerate_id($deleteOldData);
    }

    /**
     * Destroy Session
     * @return bool
     */
    public static function end(): bool
    {
        return SessionManager::end();
    }

    /**
     * Get Session ID
     * @return string
     */
    public static function id(): string
    {
        SessionManager::start();
        return session_id();
    }

    /**
     * Get Session Name
     * @return string
     */
    public static function name(): string
    {
        SessionManager::start();
        return session_name();
    }
}

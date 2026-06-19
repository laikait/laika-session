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

     ##################################################################################
     ################################### PUBLIC API ###################################
     ##################################################################################
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
     * @param array $cookies. Example ['name'=>'PHPSESSID'] and any other session options
     * @return void
     */
    public static function setCookies(array $cookies): void
    {
        SessionManager::setCookies($cookies);
    }

    /**
     * Set Session Key & Values
     * @param string|array $key Required Argument as key name or array with key & value
     * @param mixed $value Optional Argument. If $name is string this Param is Required.
     * @param string $for Optional Argument. It Will Set Data Like $_SESSION[$for][$key]. Default is 'APP'
     * @return void
     */
    public static function set(string|array $key, mixed $value = null, string $for = 'APP'): void
    {
        SessionManager::start();
        if (is_string($key)) {
            $arr = [$key => $value];
        }

        $for = strtoupper(trim($for));
        foreach ($arr as $k => $v) {
            $_SESSION[$for][$k] = $v;
        }
    }

    /**
     * Get Session Value From Key
     * @param string $key Required Argument
     * @param mixed $default Default Value To Return. Default is null
     * @param string $for Optional Argument. It Will Get Data Like $_SESSION[$for][$key]. Default is 'APP'
     * @return mixed
     */
    public static function get(string $key, mixed $default = null, string $for = 'APP'): mixed
    {
        SessionManager::start();
        $for = strtoupper(trim($for));
        return $_SESSION[$for][$key] ?? $default;
    }

    /**
     * Check Session Key Exist
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Check Data Like $_SESSION[$for][$key].
     * @return bool
     */
    public static function has(string $key, string $for = 'APP'): bool
    {
        SessionManager::start();
        $for = strtoupper(trim($for));
        return isset($_SESSION[$for][$key]);
    }

    /**
     * Remove Session Key if Exist
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Remove Data If $_SESSION[$for][$key] Exist.
     * @return void
     */
    public static function pop(string $key, string $for = 'APP'): void
    {
        $for = strtoupper(trim($for));
        if (self::has($key, $for)) {
            unset($_SESSION[$for][$key]);
        }
    }

    /**
     * Session Purge
     * @param string $for Optional Argument. It Will Purge Data Like $_SESSION[$for]. Default is 'APP'
     * @return void
     */
    public static function purge(string $for = 'APP'): void
    {
        $for = strtoupper(trim($for));
        if (isset($_SESSION[$for])) {
            unset($_SESSION[$for]);
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
    public static function destroy(): bool
    {
        return SessionManager::destroy();
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

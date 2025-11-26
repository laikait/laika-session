<?php

/**
 * Laika Database Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session;

class Session
{
    // Set Session Key & Values
    /**
     * @param string|array $name Required Argument as key name or array with key & value
     * @param mixed $value Optional Argument. If $name is string this Param is Required.
     * @param string $for Optional Argument. It Will Store Data Like $_SESSION[$for][$name].
     */
    public static function set(string|array $name, mixed $value = null, string $for = 'APP'): void
    {
        SessionManager::start();
        $for = strtoupper($for);

        if (is_string($name)) {
            $name = [$name => $value];
        }

        array_filter($name, function ($val, $key) use ($for) {
            $_SESSION[$for][$key] = $val;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // Get Session Value From Key
    /**
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Get Data From $_SESSION[$for][$key].
     * @return mixed
     */
    public static function get(string $key, string $for = 'APP'): mixed
    {
        SessionManager::start();
        $for = strtoupper($for);
        return $_SESSION[$for][$key] ?? null;
    }

    // Check Session Key Exist
    /**
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Check Data Like $_SESSION[$for][$key].
     * @return bool
     */
    public static function has(string $key, string $for = 'APP'): bool
    {
        SessionManager::start();
        $for = strtoupper($for);
        return isset($_SESSION[$for][$key]);
    }

    // Remove Session Key if Exist
    /**
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Remove Data If $_SESSION[$for][$key] Exist.
     */
    public static function pop(string $key, string $for = 'APP'): void
    {
        $for = strtoupper($for);
        if (self::has($key, $for)) {
            unset($_SESSION[$for][$key]);
        }
    }

    // Get All Session Key & Values
    /**
     * @param string $key Required Argument
     * @param string $for Optional Argument. It Will Remove Data If $_SESSION[$for][$key] Exist.
     * @return array
     */
    public static function all(): array
    {
        SessionManager::start();
        return $_SESSION;
    }

    // Regenerate Session ID
    /**
     * @param bool $deleteOldData Optional Argument. Default is true
     * @return bool
     */
    public static function regenerate(bool $deleteOldData = true): bool
    {
        SessionManager::start();
        return session_regenerate_id($deleteOldData);
    }

    // Destroy Session
    /**
     * @return bool
     */
    public static function end(): bool
    {
        return SessionManager::end();
    }

    // Get Session ID
    /**
     * @return string
     */
    public static function id(): string
    {
        SessionManager::start();
        return session_id();
    }

    // Get Session Name
    /**
     * @return string
     */
    public static function name(): string
    {
        SessionManager::start();
        return session_name();
    }
}

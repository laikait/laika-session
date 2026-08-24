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

class Session
{
     ########################################################################
     /*=========================== EXTERNAL API ===========================*/
     ########################################################################
    /**
     * Set Session Key & Values
     * @param string $key Session Key Name
     * @param mixed $value Session Key Value
     * @param string $for Session Set For. Example: $_SESSION[$for][$key] = $value;
     * @return void
     */
    public static function set(string $key, mixed $value, string $for = 'APP'): void
    {
        SessionManager::start();
        $for = strtoupper(trim($for));
        $_SESSION[$for][$key] = $value;
    }

    /**
     * Get Session Value From Key
     * @param string $key Session Key Name
     * @param mixed $default Session Default Key Value
     * @param string $for Session Get For. Example: $_SESSION[$for][$key] = $value;
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
        // Without this the purge silently does nothing when it is the first
        // session call of the request: $_SESSION is not populated yet, so the
        // isset() below is false and there is nothing to unset.
        SessionManager::start();

        $for = strtoupper(trim($for));
        if (isset($_SESSION[$for])) {
            unset($_SESSION[$for]);
        }
    }

    /**
     * Get All Session Key & Values
     * @param string $for Session Get For. Example: $_SESSION[$for];
     * @return array
     */
    public static function getFor(string $for = 'APP'): array
    {
        SessionManager::start();
        return $_SESSION[strtoupper(trim($for))] ?? [];
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
     * @return string Empty string when there is no active session.
     */
    public static function id(): string
    {
        SessionManager::start();
        return session_id() ?: '';
    }

    /**
     * Get Session Name
     * @return string
     */
    public static function name(): string
    {
        SessionManager::start();
        return session_name() ?: '';
    }
}

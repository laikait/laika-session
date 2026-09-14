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
     * Session Scope
     * @param string $name Scope Name. Default is 'APP'. Example: Session::scope('AUTH')->set('token', $token);
     * @return Scope
     */
    public static function scope(string $name = 'APP'): Scope
    {
        return new Scope($name);
    }

    /**
     * Set Session Key & Value in The 'APP' Scope
     * @param string $key Session Key Name
     * @param mixed $value Session Key Value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        static::scope()->set($key, $value);
    }

    /**
     * Get Session Value From Key in The 'APP' Scope
     * @param string $key Session Key Name
     * @param mixed $default Returned When The Key is Missing
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::scope()->get($key, $default);
    }

    /**
     * Check Session Key Exist in The 'APP' Scope
     * @param string $key Session Key Name
     * @return bool
     */
    public static function has(string $key): bool
    {
        return static::scope()->has($key);
    }

    /**
     * Remove Session Key From The 'APP' Scope if Exist
     * @param string $key Session Key Name
     * @return void
     */
    public static function pop(string $key): void
    {
        static::scope()->pop($key);
    }

    /**
     * Remove Every Key in The 'APP' Scope
     * @return void
     */
    public static function purge(): void
    {
        static::scope()->purge();
    }

    /**
     * Get Every Key & Value in The 'APP' Scope
     * @return array
     */
    public static function all(): array
    {
        return static::scope()->all();
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

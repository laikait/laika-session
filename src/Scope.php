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

use InvalidArgumentException;

/**
 * Session Scope
 *
 * One named slice of the session. Every key lives at $_SESSION[<SCOPE>][$key],
 * so unrelated parts of an application cannot overwrite each other's keys.
 * Names are trimmed and uppercased, the same normalisation v5's $for parameter
 * applied, so data written before v6 is still found.
 */
class Scope
{
    /** @var string $name Normalised scope name. */
    protected string $name;

    /**
     * @param string $name Scope Name. Default is 'APP'
     * @throws InvalidArgumentException When the name is empty.
     */
    public function __construct(string $name = 'APP')
    {
        $name = strtoupper(trim($name));

        if ($name === '') {
            throw new InvalidArgumentException('Session scope name cannot be empty.');
        }

        $this->name = $name;
    }

    ########################################################################
    /*=========================== EXTERNAL API ===========================*/
    ########################################################################
    /**
     * Get Scope Name
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set Session Key & Value
     * @param string $key Session Key Name
     * @param mixed $value Session Key Value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        SessionManager::start();
        $_SESSION[$this->name][$key] = $value;
    }

    /**
     * Get Session Value From Key
     * @param string $key Session Key Name
     * @param mixed $default Returned When The Key is Missing
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        SessionManager::start();
        return $_SESSION[$this->name][$key] ?? $default;
    }

    /**
     * Check Session Key Exist
     * @param string $key Session Key Name
     * @return bool False For a Key Set to null, Like isset()
     */
    public function has(string $key): bool
    {
        SessionManager::start();
        return isset($_SESSION[$this->name][$key]);
    }

    /**
     * Remove Session Key if Exist
     * @param string $key Session Key Name
     * @return void
     */
    public function pop(string $key): void
    {
        SessionManager::start();

        // Not has(): isset() is false for a key holding null, which then
        // could never be removed.
        if (is_array($_SESSION[$this->name] ?? null)) {
            unset($_SESSION[$this->name][$key]);
        }
    }

    /**
     * Remove Every Key in This Scope
     * @return void
     */
    public function purge(): void
    {
        // Without the start, a purge as the first session call of the request
        // runs before $_SESSION is populated and silently does nothing.
        SessionManager::start();
        unset($_SESSION[$this->name]);
    }

    /**
     * Get Every Key & Value in This Scope
     * @return array Empty When The Scope Holds Nothing
     */
    public function all(): array
    {
        SessionManager::start();
        $data = $_SESSION[$this->name] ?? [];
        return is_array($data) ? $data : [];
    }
}

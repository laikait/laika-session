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

namespace Laika\Session\Service;

use Laika\Relay\Relay;

/**
 * @method static void      config(null|PDO|Redis|Memcached $instance = null, array $args = ['prefix' => 'LK'])
 * @method static void      setOptions(array $options)
 * @method static void      setCookies(array $cookies)
 * @method static void      set(string|array $key, mixed $value = null, string $for = 'APP')
 * @method static mixed     get(string $key, mixed $default = null, string $for = 'APP')
 * @method static bool      has(string $key, string $for = 'APP')
 * @method static void      pop(string $key, string $for = 'APP')
 * @method static void      purge(string $for = 'APP')
 * @method static array     all()
 * @method static bool      regenerate(bool $deleteOldData = true)
 * @method static bool      destroy()
 * @method static string    id()
 * @method static string    name()
 */
class Session extends Relay
{
    public static function getRelayAccessor(): string
    {
        return 'session';
    }
}
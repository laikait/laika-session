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

namespace Laika\Session\Relay;

use Laika\Core\Relay\Relay;

/**
 * @method static string for(?string $for = null)
 * @method static void config(null|PDO|Redis|Memcached $instance = null, array $args = ['prefix' => 'LK'])
 * @method static void setOptions(array $options)
 * @method static void setCookies(array $cookies)
 * @method static void set(string|array $name, mixed $value = null)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string $key, ?string $for = null)
 * @method static void pop(string $key, ?string $for = null)
 * @method static array all()
 * @method static bool regenerate(bool $deleteOldData = true)
 * @method static bool end()
 * @method static string id()
 * @method static string name()
 */
class Session extends Relay
{
    public static function getRelayAccessor(): string
    {
        return 'session';
    }
}
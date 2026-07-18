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
 * @method static void      fileSessionConfig(array $params = [])
 * @method static void      dbSessionConfig(?string $connection)
 * @method static void      setOptions(array $options)
 * @method static void      setCookies(array $cookies)
 * @method static void      start()
 * @method static void      destroy()
 */
class SessionManager extends Relay
{
    public static function getRelayAccessor(): string
    {
        return 'session.manager';
    }
}
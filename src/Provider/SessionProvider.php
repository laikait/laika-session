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

namespace Laika\Session\Provider;

use Laika\Session\Session;
use Laika\Core\Relay\RelayProvider;

class SessionProvider extends RelayProvider
{
    public function register(): void
    {
        $this->registry->singleton('session', Session::class);
    }
}
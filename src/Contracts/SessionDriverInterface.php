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

namespace Laika\Session\Contracts;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Session Driver Interface
 *
 * SessionUpdateTimestampHandlerInterface is part of the contract on purpose.
 * PHP's lazy_write skips write() whenever the session data is unchanged, so a
 * driver that only refreshes its expiry inside write() lets an actively
 * browsing user's session be garbage collected out from under them.
 * updateTimestamp() is the hook that keeps a read-only request alive, and
 * validateId() is what makes use_strict_mode explicit instead of relying on
 * read() happening to return an empty string.
 */
interface SessionDriverInterface extends SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /**
     * Prepare the backing store. Called once per process, before the session starts.
     */
    public function setup(): void;
}

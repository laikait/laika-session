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

namespace Laika\Session\Handler;

use Laika\Session\Interface\SessionDriverInterface;
use Memcached;

class MemcachedSessionHandler implements SessionDriverInterface
{
    protected Memcached $memcached;

    protected int $ttl;

    public function __construct(Memcached $instance, array $args)
    {
        $this->memcached = clone $instance;
        $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $args['prefix'] ?? 'LK');
        $this->ttl = (int)($args['gc_maxlifetime'] ?? ini_get('session.gc_maxlifetime') ?: 1440);
    }

    // Session Handler Setup
    public function setup(): void
    {
        //
    }

    // Session Open
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    // Session Close
    public function close(): bool
    {
        return true;
    }

    // Session Read
    public function read($id): string
    {
        return (string)($this->memcached->get($id) ?? '');
    }

    // Session Write
    public function write($id, $data): bool
    {
        return $this->memcached->set($id, $data, $this->ttl);
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $this->memcached->delete($id);
        return true;
    }

    // Session Garbage Collection
    public function gc($maxlifetime): int
    {
        return 0;
    }
}

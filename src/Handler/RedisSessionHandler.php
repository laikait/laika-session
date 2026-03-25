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
use Redis;

class RedisSessionHandler implements SessionDriverInterface
{
    protected Redis $redis;

    protected int $ttl;

    public function __construct(Redis $instance, array $args)
    {
        $this->redis = clone $instance;
        $this->redis->setOption(Redis::OPT_PREFIX, $args['prefix'] ?? 'LK');
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
        return (string)($this->redis->get($id) ?? '');
    }

    // Session Write
    public function write($id, $data): bool
    {
        return $this->redis->setex($id, $this->ttl, $data);
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $this->redis->del($id);
        return true;
    }

    // Session Garbage Collection
    public function gc($maxlifetime): int
    {
        return 0;
    }
}

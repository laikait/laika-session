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

use Redis;
use RedisException;
use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Redis Session Driver
 *
 * The client is supplied already connected and authenticated, so this package
 * never handles credentials. Expiry is Redis' own TTL, which is why gc() has
 * nothing to do.
 */
class RedisHandler implements SessionDriverInterface
{
    /** @var Redis $client Connected and authenticated client. */
    protected Redis $client;

    /** @var string $prefix Session key prefix. */
    protected string $prefix;

    /** @var int $lifetime Key TTL in seconds. */
    protected int $lifetime;

    /**
     * @param array<string,mixed> $params Requires 'client'. Optional: 'prefix', 'lifetime'.
     */
    public function __construct(array $params)
    {
        $client = $params['client'] ?? null;
        if (!$client instanceof Redis) {
            throw new SessionHandlerException('The [redis] session driver needs a connected Redis instance.');
        }

        $this->client   = $client;
        $this->prefix   = strtoupper((string) ($params['prefix'] ?? 'LK'));
        $this->lifetime = max(1, (int) ($params['lifetime'] ?? ini_get('session.gc_maxlifetime') ?: 1440));
    }

    // Setup Handler
    public function setup(): void
    {
        //
    }

    // Session Open
    public function open(string $path, string $name): bool
    {
        return true;
    }

    // Session Close
    public function close(): bool
    {
        return true;
    }

    // Session Read
    public function read(string $id): string
    {
        try {
            $data = $this->client->get($this->key($id));
        } catch (RedisException) {
            return '';
        }

        return is_string($data) ? $data : '';
    }

    // Session Write
    public function write(string $id, string $data): bool
    {
        try {
            return (bool) $this->client->setex($this->key($id), $this->lifetime, $data);
        } catch (RedisException) {
            return false;
        }
    }

    // Session Destroy
    public function destroy(string $id): bool
    {
        try {
            $this->client->del($this->key($id));
        } catch (RedisException) {
            return false;
        }

        return true;
    }

    // Redis expires keys itself, so there is nothing to collect
    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    // Whether the id already has a session key
    public function validateId(string $id): bool
    {
        try {
            return (bool) $this->client->exists($this->key($id));
        } catch (RedisException) {
            return false;
        }
    }

    /**
     * Re-arm the TTL without rewriting the payload.
     *
     * lazy_write skips write() when the data has not changed, so without this
     * the key keeps counting down and an actively browsing user is logged out
     * mid-session.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            return (bool) $this->client->expire($this->key($id), $this->lifetime);
        } catch (RedisException) {
            return false;
        }
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * @param string $id
     * @return string Prefixed session key.
     */
    protected function key(string $id): string
    {
        return $this->prefix . '_' . $id;
    }
}

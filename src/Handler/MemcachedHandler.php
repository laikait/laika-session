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

use Memcached;
use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Memcached Session Driver
 *
 * The client is supplied already configured, so this package never handles
 * credentials or server lists. Expiry is Memcached's own TTL, which is why
 * gc() has nothing to do.
 */
class MemcachedHandler implements SessionDriverInterface
{
    /** @var Memcached $client Configured client with at least one server. */
    protected Memcached $client;

    /** @var string $prefix Session key prefix. */
    protected string $prefix;

    /** @var int $lifetime Item TTL in seconds. */
    protected int $lifetime;

    /**
     * @param array<string,mixed> $params Requires 'client'. Optional: 'prefix', 'lifetime'.
     */
    public function __construct(array $params)
    {
        $client = $params['client'] ?? null;
        if (!$client instanceof Memcached) {
            throw new SessionHandlerException('The [memcached] session driver needs a configured Memcached instance.');
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
        $data = $this->client->get($this->key($id));
        return is_string($data) ? $data : '';
    }

    // Session Write
    public function write(string $id, string $data): bool
    {
        return $this->client->set($this->key($id), $data, $this->lifetime);
    }

    // Session Destroy
    public function destroy(string $id): bool
    {
        $this->client->delete($this->key($id));

        // A session that was never stored is already destroyed as far as PHP
        // is concerned, so a miss is not a failure.
        return in_array(
            $this->client->getResultCode(),
            [Memcached::RES_SUCCESS, Memcached::RES_NOTFOUND],
            true
        );
    }

    // Memcached expires items itself, so there is nothing to collect
    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    // Whether the id already has a session item
    public function validateId(string $id): bool
    {
        $this->client->get($this->key($id));
        return $this->client->getResultCode() === Memcached::RES_SUCCESS;
    }

    /**
     * Re-arm the TTL without rewriting the payload.
     *
     * lazy_write skips write() when the data has not changed, so without this
     * the item keeps counting down and an actively browsing user is logged out
     * mid-session.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        if ($this->client->touch($this->key($id), $this->lifetime)) {
            return true;
        }

        // Not every server build supports touch, so fall back to a full write
        // rather than letting the session quietly expire.
        return $this->write($id, $data);
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

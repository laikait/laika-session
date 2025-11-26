<?php

/**
 * Laika Database Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session\Handler;

use Laika\Session\Interface\SessionDriverInterface;
use RuntimeException;
use Memcached;

class MemcachedSessionHandler implements SessionDriverInterface
{
    protected Memcached $memcached;

    public function __construct(array|Memcached $config)
    {
        if (is_array($config)) {
            $config['host'] ??= '127.0.0.1';
            $config['port'] ??= 11211;
            $config['prefix'] ??= 'CBMASTER';
            // Remove All Special Characters
            $config['prefix'] = strtoupper(preg_replace('/[^a-zA-Z_]/', '', $config['prefix']));
            try {
                $this->memcached = new Memcached();
                $this->memcached->addServer($config['host'], $config['port']);
                $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $config['prefix']);
            } catch (RuntimeException $e) {
                throw $e;
            }
        } else {
            $this->memcached = $config;
        }
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
        return $this->memcached->set($id, $data);
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $this->memcached->delete($id);
        return true;
    }

    // Session Garbase Collection
    public function gc($maxlifetime): int
    {
        return 0;
    }
}

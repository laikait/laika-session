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
use InvalidArgumentException;
use RuntimeException;
use Exception;
use Redis;

class RedisSessionHandler implements SessionDriverInterface
{
    protected Redis $redis;

    public function __construct(array|Redis $config)
    {
        if (is_array($config)) {
            $config['host'] ??= '127.0.0.1';
            $config['port'] ??= 6379;
            $config['timeout'] ??= 2.0;
            $config['prefix'] ??= 'CBMASTER';
            $config['prefix'] = strtoupper(preg_replace('/[^a-zA-Z_]/', '', $config['prefix']));
            try {
                $this->redis = new Redis();
                $this->redis->connect($config['host'], (int)$config['port'], (float)$config['timeout']);
            } catch (InvalidArgumentException $e) {
                throw $e;
            }
            // Set Auth if Password Exist
            if (isset($config['password']) && $config['password']) {
                try {
                    $this->redis->auth($config['password']);
                } catch (Exception $e) {
                    throw $e;
                }
            }
            $this->redis->setOption(Redis::OPT_PREFIX, $config['prefix']);
        } else {
            $this->redis = $config;
        }
        if (!($this->redis instanceof Redis)) {
            throw new RuntimeException('Invalid Instance Provided!');
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
        return (string)($this->redis->get($id) ?? '');
    }

    // Session Write
    public function write($id, $data): bool
    {
        return $this->redis->set($id, $data);
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $this->redis->del($id);
        return true;
    }

    // Session Garbase Collection
    public function gc($maxlifetime): int
    {
        return 0;
    }
}

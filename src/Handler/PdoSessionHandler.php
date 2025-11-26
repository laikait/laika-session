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
use PDO;

class PdoSessionHandler implements SessionDriverInterface
{
    // PDO Instance
    protected PDO $pdo;

    public function __construct(array|PDO $config)
    {
        if (is_array($config)) {
            if (!isset($config['dsn'])) {
                throw new InvalidArgumentException("Key 'dsn' not Found!");
            }
            if (!isset($config['username'])) {
                throw new InvalidArgumentException("Key 'username' not Found!");
            }
            if (!isset($config['password'])) {
                throw new InvalidArgumentException("Key 'password' not Found!");
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            $this->pdo = new PDO($config['dsn'], $config['username'], $config['password'], $options);
        } else {
            $this->pdo = $config;
        }

        if (!($this->pdo instanceof PDO)) {
            throw new RuntimeException('Invalid Instance Provided!');
        }
    }

    // Session Handler Setup
    public function setup(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `sessions` (
            `id` VARCHAR(128) PRIMARY KEY,
            `data` BLOB,
            `timestamp` INT
        )";
        $this->pdo->exec($sql);
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
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?? '');
    }

    // Session Write
    public function write($id, $data): bool
    {
        $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, timestamp) VALUES (?, ?, ?)");
        return $stmt->execute([$id, $data, time()]);
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }

    // Session Garbase Collection
    public function gc($maxlifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE timestamp < ?");
        $stmt->execute([time() - $maxlifetime]);
        return $stmt->rowCount();
    }
}

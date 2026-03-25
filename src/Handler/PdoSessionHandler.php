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
use PDO;

class PdoSessionHandler implements SessionDriverInterface
{
    // PDO Instance
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Session Handler Setup
    public function setup(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `sessions` (
            `id` VARCHAR(128) PRIMARY KEY,
            `data` BLOB,
            `last_activity` INT
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
        $stmt = $this->pdo->prepare("INSERT INTO sessions (id, data, `last_activity`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), `last_activity` = VALUES(`last_activity`)");
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
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
        $stmt->execute([time() - $maxlifetime]);
        return $stmt->rowCount();
    }
}

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

use PDO;
use PDOException;
use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * MySQL Session Driver
 *
 * Talks to PDO directly and owns its SQL, so a database-backed session needs
 * nothing but ext-pdo. The table layout matches SessionSchema, so this driver
 * and ModelHandler can share one table and be swapped without a migration.
 */
class MySQLHandler implements SessionDriverInterface
{
    /** @var PDO $pdo Connected instance, supplied by the caller. */
    protected PDO $pdo;

    /** @var string $table Session table name. */
    protected string $table;

    /** @var bool $install Whether setup() should create the table. */
    protected bool $install;

    /**
     * @param array<string,mixed> $params Requires 'pdo'. Optional: 'table', 'install'.
     */
    public function __construct(array $params)
    {
        $pdo = $params['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw new SessionHandlerException('The [mysql] session driver needs a connected PDO instance.');
        }

        $table = (string) ($params['table'] ?? 'sessions');

        // The table name is interpolated into the SQL, so it is validated here
        // rather than trusted. Everything else in these statements is bound.
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new SessionHandlerException("Session table [{$table}] is not a valid table name.");
        }

        $this->pdo     = $pdo;
        $this->table   = $table;
        $this->install = (bool) ($params['install'] ?? false);
    }

    // Create the table when explicitly asked to
    public function setup(): void
    {
        if (!$this->install) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->table() . ' ('
            . '`id` VARCHAR(128) NOT NULL,'
            . '`data` BLOB NULL,'
            . '`last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (`id`),'
            . 'KEY `idx_last_activity` (`last_activity`)'
            . ')'
        );
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
        $stmt = $this->pdo->prepare('SELECT `data` FROM ' . $this->table() . ' WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);

        $data = $stmt->fetchColumn();
        $stmt->closeCursor();

        // A BLOB column comes back as a stream on some drivers and a string on
        // others, so normalise instead of assuming.
        if (is_resource($data)) {
            return (string) (stream_get_contents($data) ?: '');
        }

        return $data === false || $data === null ? '' : (string) $data;
    }

    // Session Write
    public function write(string $id, string $data): bool
    {
        // One statement, so two concurrent first-writes for the same id cannot
        // collide the way select-then-insert does.
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $this->table() . ' (`id`, `data`, `last_activity`) VALUES (:id, :data, :now) '
            . 'ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `last_activity` = VALUES(`last_activity`)'
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->bindValue(':data', $data, PDO::PARAM_LOB);
        $stmt->bindValue(':now', $this->now(), PDO::PARAM_STR);

        return $stmt->execute();
    }

    // Session Destroy
    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->table() . ' WHERE `id` = ?');
        return $stmt->execute([$id]);
    }

    // Session Garbage Collection
    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->table() . ' WHERE `last_activity` < ?');

        if (!$stmt->execute([$this->now(time() - $max_lifetime)])) {
            return false;
        }

        return $stmt->rowCount();
    }

    // Whether the id already has a session row
    public function validateId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ' . $this->table() . ' WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);

        $found = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $found !== false;
    }

    /**
     * Refresh last_activity without rewriting the payload.
     *
     * lazy_write skips write() when the data has not changed, so without this
     * last_activity never advances for a user who is browsing without writing
     * and gc() eventually deletes a session that is still in use.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ' . $this->table() . ' SET `last_activity` = ? WHERE `id` = ?');

        try {
            return $stmt->execute([$this->now(), $id]);
        } catch (PDOException) {
            return false;
        }
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * @return string Quoted table identifier.
     */
    protected function table(): string
    {
        return '`' . $this->table . '`';
    }

    /**
     * @param ?int $timestamp Defaults to now.
     * @return string A TIMESTAMP-compatible datetime.
     */
    protected function now(?int $timestamp = null): string
    {
        return date('Y-m-d H:i:s', $timestamp ?? time());
    }
}

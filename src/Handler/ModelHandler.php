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

use Laika\Session\Model\SessionModel;
use Laika\Session\Schema\SessionSchema;
use Laika\Session\Contracts\SessionDriverInterface;

/**
 * Laika Model Session Driver
 *
 * A thin delegate over SessionModel, which owns the queries. Requires
 * laikait/laika-model; use MySQLHandler for a database session without it.
 */
class ModelHandler implements SessionDriverInterface
{
    /** @var SessionModel $model */
    protected SessionModel $model;

    /** @var ?string $connection Connection name, null for laika-model's default. */
    protected ?string $connection;

    /** @var bool $install Whether setup() should create the table. */
    protected bool $install;

    /**
     * @param array<string,mixed> $params Optional: 'connection', 'install'.
     */
    public function __construct(array $params = [])
    {
        $connection = $params['connection'] ?? null;

        $this->connection = is_string($connection) ? $connection : null;
        $this->model      = new SessionModel($this->connection);
        $this->install    = (bool) ($params['install'] ?? false);
    }

    // Create the table when explicitly asked to
    public function setup(): void
    {
        if ($this->install) {
            // The schema has to target the same connection the model reads and
            // writes on. Constructing it bare pins it to 'default', so a session
            // configured for another connection created its table in the wrong
            // database -- or failed outright when 'default' was never registered.
            (new SessionSchema($this->connection))->up();
        }
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
        return $this->model->readData($id);
    }

    // Session Write
    public function write(string $id, string $data): bool
    {
        return $this->model->writeData($id, $data);
    }

    // Session Destroy
    public function destroy(string $id): bool
    {
        $this->model->deleteId($id);
        return true;
    }

    // Session Garbage Collection
    public function gc(int $max_lifetime): int|false
    {
        return $this->model->deleteExpired($max_lifetime);
    }

    // Whether the id already has a session row
    public function validateId(string $id): bool
    {
        return $this->model->hasId($id);
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
        return $this->model->touch($id);
    }
}

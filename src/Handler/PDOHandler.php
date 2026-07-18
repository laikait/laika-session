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
use Laika\Session\Model\SessionModel;
use Laika\Session\Schema\SessionSchema;
use Laika\Session\Interface\SessionDriverInterface;

class PDOHandler implements SessionDriverInterface
{
    /** @var SessionModel Instance */
    protected SessionModel $model;

    /**
     * @param ?string $connection Connection Name
     */
    public function __construct(?string $connection = null)
    {
        $this->model = new SessionModel($connection);
    }

    /**
     * Session Database Setup
     */
    public function setup(): void
    {
        (new SessionSchema())->up();
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
        $row = $this->model->select('data')->where([$this->model->id => $id])->first();
        return $row['data'] ?? '';
    }

    // Session Write
    public function write($id, $data): bool
    {
        $row = $this->model->select('id')->where([$this->model->id => $id])->first();
        $date = date('Y-m-d H:i:s');
        isset($row['id']) ?
            $this->model->where([$this->model->id => $id])->update(['data' => $data, 'last_activity' => date('Y-m-d H:i:s')]) :
            $this->model->insert([$this->model->id => $id, 'data' => $data, 'last_activity' => date('Y-m-d H:i:s')]);
        return true;
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $this->model->where([$this->model->id => $id])->delete();
        return true;
    }

    // Session Garbase Collection
    public function gc($maxlifetime): int|false
    {
        return $this->model->where(['last_activity' => date('Y-m-d H:i:s')], '<')->delete();
    }
}

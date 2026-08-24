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

namespace Laika\Session\Model;

use Throwable;
use Laika\Model\Model;

/**
 * Session Model
 *
 * The session queries live here rather than in ModelHandler on purpose. The
 * primary key name ($id) and the fetch-mode normaliser (rowGet) are both
 * protected on Model, so a handler reaching in from outside raises
 * "Cannot access protected property". This class is the only place that can
 * legally touch them.
 */
class SessionModel extends Model
{
    /** @var string Table Name */
    protected string $table = 'sessions';

    /**
     * Read a session payload.
     * @param string $sid Session ID
     * @return string Empty string when the session does not exist.
     */
    public function readData(string $sid): string
    {
        $row = $this->select('data')->where([$this->id => $sid])->first();

        if ($row === null) {
            return '';
        }

        // rowGet() is Laika\Model\Model::rowGet() (laika-model/src/Model.php:1535),
        // sitting alongside rowHas() and rowSet(). It exists because rows are
        // arrays under PDO::FETCH_ASSOC and stdClass under FETCH_OBJ, and the
        // connection's options decide which. It is protected, so only a subclass
        // can reach it -- which is why these queries live here and not in
        // ModelHandler.
        $data = $this->rowGet($row, 'data');

        // A BLOB column comes back as a stream on some drivers and a string on
        // others, so normalise instead of assuming.
        if (is_resource($data)) {
            return (string) (stream_get_contents($data) ?: '');
        }

        return $data === null ? '' : (string) $data;
    }

    /**
     * Insert or update a session payload.
     * @param string $sid Session ID
     * @param string $data Serialized session payload
     * @return bool Whether the row was actually persisted.
     */
    public function writeData(string $sid, string $data): bool
    {
        $row = [$this->id => $sid, 'data' => $data, 'last_activity' => $this->now()];

        if ($this->hasId($sid)) {
            return $this->touchRow($sid, $data);
        }

        try {
            return $this->insert($row) !== false;
        } catch (Throwable) {
            // Two concurrent first-writes for one id race between the check
            // above and this insert. The loser lost a primary key collision,
            // not the session, so finish as an update.
            return $this->touchRow($sid, $data);
        }
    }

    /**
     * Refresh last_activity without rewriting the payload.
     * @param string $sid Session ID
     * @return bool
     */
    public function touch(string $sid): bool
    {
        return $this->where([$this->id => $sid])->update(['last_activity' => $this->now()]) > 0;
    }

    /**
     * Whether a session row exists.
     * @param string $sid Session ID
     * @return bool
     */
    public function hasId(string $sid): bool
    {
        return $this->where([$this->id => $sid])->exists();
    }

    /**
     * Delete one session.
     * @param string $sid Session ID
     * @return int Rows deleted.
     */
    public function deleteId(string $sid): int
    {
        return $this->where([$this->id => $sid])->delete();
    }

    /**
     * Delete every session older than the given lifetime.
     * @param int $maxlifetime Seconds
     * @return int Rows deleted.
     */
    public function deleteExpired(int $maxlifetime): int
    {
        return $this->where(['last_activity' => $this->now(time() - $maxlifetime)], '<')->delete();
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * Update an existing session row.
     * @param string $sid Session ID
     * @param string $data Serialized session payload
     * @return bool
     */
    protected function touchRow(string $sid, string $data): bool
    {
        $this->where([$this->id => $sid])->update(['data' => $data, 'last_activity' => $this->now()]);

        // An update that writes identical bytes reports zero affected rows on
        // MySQL, so the row count cannot distinguish "no change" from "no row".
        return $this->hasId($sid);
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

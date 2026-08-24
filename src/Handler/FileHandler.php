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

use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * File Session Driver
 *
 * The lock is held from read() all the way to write()/close(), which is what
 * PHP's own files handler does. Locking only the individual writes would still
 * let two concurrent requests interleave read-modify-write and silently drop
 * one of them.
 */
class FileHandler implements SessionDriverInterface
{
    /** @var string $path Session save path. */
    protected string $path;

    /** @var string $prefix Session file prefix. */
    protected string $prefix;

    /** @var ?resource $handle Open, exclusively locked handle for the current session. */
    protected $handle = null;

    /** @var ?string $handleId Session id $handle belongs to. */
    protected ?string $handleId = null;

    /**
     * @param array<string,mixed> $params Example: ['path' => '/var/sessions', 'prefix' => 'LK']
     */
    public function __construct(array $params = [])
    {
        $this->path   = $this->resolvePath($params['path'] ?? null);
        $this->prefix = strtoupper((string) ($params['prefix'] ?? 'LK'));
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
        $this->release();
        return true;
    }

    // Session Read
    public function read(string $id): string
    {
        if (!$this->isValidId($id)) {
            return '';
        }

        $this->release();

        // 'c+' creates when missing and never truncates, so the lock can be taken
        // before we know whether the session exists.
        $handle = @fopen($this->file($id), 'c+');
        if ($handle === false) {
            return '';
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return '';
        }

        @chmod($this->file($id), 0600);

        $this->handle   = $handle;
        $this->handleId = $id;

        $size = (int) (fstat($handle)['size'] ?? 0);
        if ($size === 0) {
            return '';
        }

        rewind($handle);
        return (string) (stream_get_contents($handle) ?: '');
    }

    // Session Write
    public function write(string $id, string $data): bool
    {
        if (!$this->isValidId($id)) {
            return false;
        }

        // Reuse the handle read() locked. session_regenerate_id() can write a
        // different id than the one we hold, so fall back to a fresh lock.
        if ($this->handle === null || $this->handleId !== $id) {
            $this->release();

            $handle = @fopen($this->file($id), 'c+');
            if ($handle === false || !flock($handle, LOCK_EX)) {
                if ($handle !== false) {
                    fclose($handle);
                }
                return false;
            }

            @chmod($this->file($id), 0600);

            $this->handle   = $handle;
            $this->handleId = $id;
        }

        rewind($this->handle);
        if (!ftruncate($this->handle, 0)) {
            return false;
        }

        if (fwrite($this->handle, $data) === false) {
            return false;
        }

        return fflush($this->handle);
    }

    // Session Destroy
    public function destroy(string $id): bool
    {
        if (!$this->isValidId($id)) {
            return false;
        }

        if ($this->handleId === $id) {
            $this->release();
        }

        $file = $this->file($id);
        return file_exists($file) ? @unlink($file) : true;
    }

    // Session Garbage Collection
    public function gc(int $max_lifetime): int|false
    {
        $count  = 0;
        $cutoff = time() - $max_lifetime;

        // glob() returns false on failure, and foreach over false is a TypeError.
        $files = glob($this->path . DIRECTORY_SEPARATOR . $this->prefix . '_*') ?: [];

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    // Whether the id is well formed and already has a session on disk
    public function validateId(string $id): bool
    {
        return $this->isValidId($id) && file_exists($this->file($id));
    }

    /**
     * Refresh the expiry without rewriting the payload.
     *
     * lazy_write skips write() when the data has not changed, so without this an
     * actively browsing user's file keeps its original mtime and gc() eventually
     * deletes a session that is still in use.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        if (!$this->isValidId($id)) {
            return false;
        }

        return @touch($this->file($id));
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * Resolve and validate the save path.
     * @param mixed $path Configured path, or null to fall back to the ini setting.
     * @return string
     */
    protected function resolvePath(mixed $path): string
    {
        if (!is_string($path) || trim($path) === '') {
            $path = session_save_path();
        }

        // session_save_path() is empty on plenty of installs, where PHP uses the
        // system temp dir. realpath('') is false, so resolve that here rather
        // than letting false hit the string-typed property as a TypeError.
        if (!is_string($path) || trim($path) === '') {
            $path = sys_get_temp_dir();
        }

        $resolved = realpath(rtrim($path, '/\\'));

        if ($resolved === false || !is_dir($resolved)) {
            throw new SessionHandlerException("Session path [{$path}] is invalid or doesn't exists!!");
        }

        return $resolved;
    }

    /**
     * Absolute path of a session file.
     * @param string $id
     * @return string
     */
    protected function file(string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->prefix . '_' . $id;
    }

    /**
     * Session ids reach the filesystem, so anything outside PHP's own id
     * alphabet is rejected rather than escaped.
     * @param string $id
     * @return bool
     */
    protected function isValidId(string $id): bool
    {
        return $id !== '' && preg_match('/^[A-Za-z0-9,\-]+$/', $id) === 1;
    }

    /**
     * Unlock and close the held handle.
     * @return void
     */
    protected function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }

        $this->handle   = null;
        $this->handleId = null;
    }
}

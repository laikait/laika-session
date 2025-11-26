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

class FileSessionHandler implements SessionDriverInterface
{
    /**
     * Session Save Path
     * @var string $path
     */
    protected string $path;

    /**
     * Session File Prefix
     * @var string $prefix
     */
    protected string $prefix;

    public function __construct(?array $config = null)
    {
        $this->path = $config['path'] ?? session_save_path();
        $this->path = rtrim($this->path, '/\\');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
        $this->prefix = strtoupper($config['prefix'] ?? 'CBMASTER');
    }

    // Setup Handler
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
        $file = "{$this->path}/{$this->prefix}_{$id}";
        return file_exists($file) ? file_get_contents($file) : '';
    }

    // Session Write
    public function write($id, $data): bool
    {
        return file_put_contents("{$this->path}/{$this->prefix}_{$id}", $data) !== false;
    }

    // Session Destroy
    public function destroy($id): bool
    {
        $file = "{$this->path}/{$this->prefix}_{$id}";
        return file_exists($file) ? unlink($file) : true;
    }

    // Session Garbase Collection
    public function gc($maxlifetime): int|false
    {
        $count = 0;
        $files = glob("{$this->path}/{$this->prefix}_*");
        foreach ($files as $file) {
            if ((filemtime($file) + $maxlifetime) < time()) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}

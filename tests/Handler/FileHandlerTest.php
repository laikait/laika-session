<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\FileHandler;
use Laika\Session\Exceptions\SessionHandlerException;

class FileHandlerTest extends TestCase
{
    protected string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laika-file-test-' . bin2hex(random_bytes(4));
        mkdir($this->path);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->path);
    }

    protected function handler(array $params = []): FileHandler
    {
        return new FileHandler(array_merge(['path' => $this->path, 'prefix' => 'TS'], $params));
    }

    protected function file(string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . 'TS_' . $id;
    }

    #[Test]
    public function it_round_trips_a_session(): void
    {
        $handler = $this->handler();

        $this->assertTrue($handler->write('abc123', 'payload'));
        $handler->close();

        $this->assertSame('payload', $handler->read('abc123'));
        $handler->close();
    }

    #[Test]
    public function reading_an_unknown_session_returns_an_empty_string(): void
    {
        $handler = $this->handler();

        $this->assertSame('', $handler->read('nothinghere'));
        $handler->close();
    }

    #[Test]
    public function a_rewrite_shrinks_the_file(): void
    {
        $handler = $this->handler();

        $handler->write('abc123', 'a-much-longer-payload');
        $handler->close();

        $handler->read('abc123');
        $handler->write('abc123', 'short');
        $handler->close();

        // Without ftruncate the tail of the previous, longer payload survives.
        $this->assertSame('short', file_get_contents($this->file('abc123')));
    }

    #[Test]
    public function session_files_are_not_world_readable(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permission bits are not meaningful on Windows.');
        }

        $handler = $this->handler();
        $handler->write('abc123', 'payload');
        $handler->close();

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->file('abc123'))), -4));
    }

    #[Test]
    public function destroy_removes_the_file(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');
        $handler->close();

        $this->assertTrue($handler->destroy('abc123'));
        $this->assertFileDoesNotExist($this->file('abc123'));
    }

    #[Test]
    public function destroying_an_unknown_session_succeeds(): void
    {
        $this->assertTrue($this->handler()->destroy('nothinghere'));
    }

    #[Test]
    public function gc_keeps_live_sessions_and_deletes_expired_ones(): void
    {
        $handler = $this->handler();

        $handler->write('fresh', 'payload');
        $handler->close();
        $handler->write('stale', 'payload');
        $handler->close();

        touch($this->file('stale'), time() - 5000);

        $this->assertSame(1, $handler->gc(1440));
        $this->assertFileExists($this->file('fresh'));
        $this->assertFileDoesNotExist($this->file('stale'));
    }

    #[Test]
    public function gc_on_an_empty_directory_returns_zero(): void
    {
        // glob() returning false here used to be a TypeError, not a zero.
        $this->assertSame(0, $this->handler()->gc(1440));
    }

    #[Test]
    public function update_timestamp_keeps_a_browsing_session_alive(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');
        $handler->close();

        // lazy_write skips write() when the payload is unchanged, so without
        // updateTimestamp() the mtime never advances and gc() evicts a session
        // that is still in use.
        touch($this->file('abc123'), time() - 5000);
        $this->assertTrue($handler->updateTimestamp('abc123', 'payload'));

        $this->assertSame(0, $handler->gc(1440));
        $this->assertFileExists($this->file('abc123'));
    }

    #[Test]
    public function validate_id_reports_whether_the_session_exists(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');
        $handler->close();

        $this->assertTrue($handler->validateId('abc123'));
        $this->assertFalse($handler->validateId('nothinghere'));
    }

    #[Test]
    public function it_rejects_ids_that_would_escape_the_save_path(): void
    {
        $handler = $this->handler();

        $this->assertFalse($handler->write('../escape', 'payload'));
        $this->assertSame('', $handler->read('../escape'));
        $this->assertFalse($handler->validateId('../escape'));
    }

    #[Test]
    public function an_invalid_path_raises_the_documented_exception(): void
    {
        // realpath() returns false here, which used to hit the string-typed
        // property as a TypeError before the is_dir() guard could run.
        $this->expectException(SessionHandlerException::class);

        $this->handler(['path' => $this->path . DIRECTORY_SEPARATOR . 'does-not-exist']);
    }

    #[Test]
    public function an_empty_path_falls_back_to_the_system_temp_dir(): void
    {
        $handler = new FileHandler(['path' => '', 'prefix' => 'TS']);

        $this->assertInstanceOf(FileHandler::class, $handler);
    }
}

<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use Memcached;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\MemcachedHandler;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Needs ext-memcached and a reachable server. Point it somewhere with
 * LAIKA_SESSION_MEMCACHED_HOST / LAIKA_SESSION_MEMCACHED_PORT; skips otherwise.
 */
class MemcachedHandlerTest extends TestCase
{
    protected ?Memcached $client = null;

    protected function setUp(): void
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('ext-memcached is not installed.');
        }

        $host = getenv('LAIKA_SESSION_MEMCACHED_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('LAIKA_SESSION_MEMCACHED_PORT') ?: 11211);

        $client = new Memcached();
        $client->addServer($host, $port);

        // addServer() does not connect, so probe before trusting the server.
        $client->set('TS_probe', '1', 5);
        if ($client->getResultCode() !== Memcached::RES_SUCCESS) {
            $this->markTestSkipped('No Memcached server available.');
        }

        $this->client = $client;
    }

    protected function tearDown(): void
    {
        if ($this->client instanceof Memcached) {
            $this->client->delete('TS_probe');
            $this->client->delete('TS_abc123');
        }
    }

    protected function handler(array $params = []): MemcachedHandler
    {
        return new MemcachedHandler(array_merge(
            ['client' => $this->client, 'prefix' => 'TS', 'lifetime' => 1440],
            $params
        ));
    }

    #[Test]
    public function it_round_trips_a_session(): void
    {
        $handler = $this->handler();

        $this->assertTrue($handler->write('abc123', 'payload'));
        $this->assertSame('payload', $handler->read('abc123'));
    }

    #[Test]
    public function reading_an_unknown_session_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->handler()->read('nothinghere'));
    }

    #[Test]
    public function destroy_removes_the_item(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->destroy('abc123'));
        $this->assertSame('', $handler->read('abc123'));
    }

    #[Test]
    public function destroying_an_unknown_session_succeeds(): void
    {
        // A session that was never stored is already destroyed as far as PHP
        // is concerned, so a miss must not read as a failure.
        $this->assertTrue($this->handler()->destroy('nothinghere'));
    }

    #[Test]
    public function update_timestamp_keeps_the_session_readable(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->updateTimestamp('abc123', 'payload'));
        $this->assertSame('payload', $handler->read('abc123'));
    }

    #[Test]
    public function gc_is_a_no_op_because_memcached_expires_items_itself(): void
    {
        $this->assertSame(0, $this->handler()->gc(1440));
    }

    #[Test]
    public function validate_id_reports_whether_the_session_exists(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->validateId('abc123'));
        $this->assertFalse($handler->validateId('nothinghere'));
    }

    #[Test]
    public function it_rejects_a_missing_client(): void
    {
        $this->expectException(SessionHandlerException::class);

        new MemcachedHandler([]);
    }
}

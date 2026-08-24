<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use Redis;
use RedisException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\RedisHandler;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Needs ext-redis and a reachable server. Point it somewhere with
 * LAIKA_SESSION_REDIS_HOST / LAIKA_SESSION_REDIS_PORT; skips cleanly otherwise.
 */
class RedisHandlerTest extends TestCase
{
    protected ?Redis $client = null;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not installed.');
        }

        $host = getenv('LAIKA_SESSION_REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('LAIKA_SESSION_REDIS_PORT') ?: 6379);

        try {
            $client = new Redis();
            if (!@$client->connect($host, $port, 1.0)) {
                $this->markTestSkipped('No Redis server available.');
            }
        } catch (RedisException $e) {
            $this->markTestSkipped('No Redis server available: ' . $e->getMessage());
        }

        $this->client = $client;
    }

    protected function tearDown(): void
    {
        if ($this->client instanceof Redis) {
            foreach ($this->client->keys('TS_*') ?: [] as $key) {
                $this->client->del($key);
            }

            $this->client->close();
        }
    }

    protected function handler(array $params = []): RedisHandler
    {
        return new RedisHandler(array_merge(
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
    public function it_sets_a_ttl_from_the_configured_lifetime(): void
    {
        $handler = $this->handler(['lifetime' => 120]);
        $handler->write('abc123', 'payload');

        $ttl = $this->client->ttl('TS_abc123');

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }

    #[Test]
    public function update_timestamp_re_arms_the_ttl(): void
    {
        $handler = $this->handler(['lifetime' => 600]);
        $handler->write('abc123', 'payload');

        $this->client->expire('TS_abc123', 5);

        // lazy_write skips write() when the payload is unchanged, so without
        // this the key keeps counting down and logs the user out mid-session.
        $this->assertTrue($handler->updateTimestamp('abc123', 'payload'));
        $this->assertGreaterThan(5, $this->client->ttl('TS_abc123'));
    }

    #[Test]
    public function destroy_removes_the_key(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->destroy('abc123'));
        $this->assertSame('', $handler->read('abc123'));
    }

    #[Test]
    public function gc_is_a_no_op_because_redis_expires_keys_itself(): void
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

        new RedisHandler([]);
    }
}

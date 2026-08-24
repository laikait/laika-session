<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use PDO;
use PDOException;
use Laika\Model\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\ModelHandler;

/**
 * Exercises the laika-model driver against a real database.
 *
 * laika-model resolves connections by name through Laika\Model\Connection, so
 * the connection has to be registered here before SessionModel can use it --
 * setting an env var alone is not enough. Reuses the same MySQL credentials as
 * MySQLHandlerTest (LAIKA_SESSION_DSN / _USER / _PASS) so one database serves
 * both DB drivers, and skips cleanly when no server is reachable.
 */
class ModelHandlerTest extends TestCase
{
    protected const CONNECTION = 'laika_session_model_test';

    protected function setUp(): void
    {
        if (!class_exists(\Laika\Model\Model::class)) {
            $this->markTestSkipped('laikait/laika-model is not installed.');
        }

        $host = getenv('LAIKA_SESSION_MYSQL_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('LAIKA_SESSION_MYSQL_PORT') ?: 3306);
        $db   = getenv('LAIKA_SESSION_MYSQL_DB') ?: 'laika_session_test';
        $user = getenv('LAIKA_SESSION_USER') ?: 'root';
        $pass = getenv('LAIKA_SESSION_PASS') ?: '';

        // Probe with plain PDO first: an unreachable server should skip, not fail.
        try {
            new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $this->markTestSkipped('No MySQL server available for the model driver: ' . $e->getMessage());
        }

        Connection::add([
            'driver'   => 'mysql',
            'host'     => $host,
            'port'     => $port,
            'database' => $db,
            'username' => $user,
            'password' => $pass,
            'charset'  => 'utf8mb4',
        ], self::CONNECTION);
    }

    protected function handler(array $params = []): ModelHandler
    {
        return new ModelHandler(array_merge(
            ['connection' => self::CONNECTION, 'install' => true],
            $params
        ));
    }

    #[Test]
    public function it_round_trips_a_session(): void
    {
        $handler = $this->handler();
        $handler->setup();

        $this->assertTrue($handler->write('abc123', 'payload'));
        $this->assertSame('payload', $handler->read('abc123'));

        $handler->destroy('abc123');
    }

    #[Test]
    public function reading_an_unknown_session_returns_an_empty_string(): void
    {
        $handler = $this->handler();
        $handler->setup();

        $this->assertSame('', $handler->read('nothinghere'));
    }

    #[Test]
    public function writing_twice_updates_rather_than_duplicating(): void
    {
        $handler = $this->handler();
        $handler->setup();

        $handler->write('abc123', 'first');
        $handler->write('abc123', 'second');

        $this->assertSame('second', $handler->read('abc123'));

        $handler->destroy('abc123');
    }

    #[Test]
    public function gc_keeps_live_sessions(): void
    {
        $handler = $this->handler();
        $handler->setup();

        $handler->write('fresh', 'payload');

        // The old predicate was last_activity < NOW(), which matched every row
        // and logged out every user roughly once per hundred requests.
        $handler->gc(1440);

        $this->assertSame('payload', $handler->read('fresh'));

        $handler->destroy('fresh');
    }

    #[Test]
    public function validate_id_reports_whether_the_session_exists(): void
    {
        $handler = $this->handler();
        $handler->setup();

        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->validateId('abc123'));
        $this->assertFalse($handler->validateId('nothinghere'));

        $handler->destroy('abc123');
    }
}

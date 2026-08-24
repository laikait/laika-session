<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\MySQLHandler;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Needs a MySQL server. Point it somewhere with:
 *   LAIKA_SESSION_DSN, LAIKA_SESSION_USER, LAIKA_SESSION_PASS
 * Defaults to a local server with the usual XAMPP credentials, and skips
 * cleanly when nothing is reachable.
 */
class MySQLHandlerTest extends TestCase
{
    protected static ?PDO $pdo = null;

    protected string $table = 'laika_session_test';

    protected function setUp(): void
    {
        $pdo = $this->pdo();

        $pdo->exec('DROP TABLE IF EXISTS `' . $this->table . '`');
        $this->handler(['install' => true])->setup();
    }

    protected function tearDown(): void
    {
        if (self::$pdo instanceof PDO) {
            self::$pdo->exec('DROP TABLE IF EXISTS `' . $this->table . '`');
        }
    }

    protected function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn  = getenv('LAIKA_SESSION_DSN') ?: 'mysql:host=127.0.0.1;dbname=laika_session_test;charset=utf8mb4';
        $user = getenv('LAIKA_SESSION_USER') ?: 'root';
        $pass = getenv('LAIKA_SESSION_PASS') ?: '';

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $this->markTestSkipped('No MySQL server available: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    protected function handler(array $params = []): MySQLHandler
    {
        return new MySQLHandler(array_merge(
            ['pdo' => $this->pdo(), 'table' => $this->table],
            $params
        ));
    }

    protected function ageSession(string $id, int $seconds): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE `' . $this->table . '` SET `last_activity` = ? WHERE `id` = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s', time() - $seconds), $id]);
    }

    #[Test]
    public function it_round_trips_a_session(): void
    {
        $handler = $this->handler();

        $this->assertTrue($handler->write('abc123', 'payload'));
        $this->assertSame('payload', $handler->read('abc123'));
    }

    #[Test]
    public function it_round_trips_binary_payloads(): void
    {
        $handler = $this->handler();
        $binary  = random_bytes(256);

        $handler->write('abc123', $binary);

        $this->assertSame($binary, $handler->read('abc123'));
    }

    #[Test]
    public function reading_an_unknown_session_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->handler()->read('nothinghere'));
    }

    #[Test]
    public function writing_twice_updates_rather_than_duplicating(): void
    {
        $handler = $this->handler();

        $handler->write('abc123', 'first');
        $handler->write('abc123', 'second');

        $this->assertSame('second', $handler->read('abc123'));

        $count = $this->pdo()
            ->query('SELECT COUNT(*) FROM `' . $this->table . '`')
            ->fetchColumn();

        $this->assertSame(1, (int) $count);
    }

    #[Test]
    public function write_reports_failure_when_the_table_is_gone(): void
    {
        $handler = $this->handler();
        $this->pdo()->exec('DROP TABLE `' . $this->table . '`');

        // A failed write used to return a hardcoded true, so the session
        // vanished with no error anywhere.
        $this->expectException(PDOException::class);
        $handler->write('abc123', 'payload');
    }

    #[Test]
    public function destroy_removes_the_row(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');

        $this->assertTrue($handler->destroy('abc123'));
        $this->assertSame('', $handler->read('abc123'));
    }

    #[Test]
    public function gc_keeps_live_sessions_and_deletes_expired_ones(): void
    {
        $handler = $this->handler();

        $handler->write('fresh', 'payload');
        $handler->write('stale', 'payload');
        $this->ageSession('stale', 5000);

        // The old predicate was last_activity < NOW(), which matched every row
        // and logged out every user roughly once per hundred requests.
        $this->assertSame(1, $handler->gc(1440));
        $this->assertSame('payload', $handler->read('fresh'));
        $this->assertSame('', $handler->read('stale'));
    }

    #[Test]
    public function update_timestamp_keeps_a_browsing_session_alive(): void
    {
        $handler = $this->handler();
        $handler->write('abc123', 'payload');
        $this->ageSession('abc123', 5000);

        $this->assertTrue($handler->updateTimestamp('abc123', 'payload'));

        $this->assertSame(0, $handler->gc(1440));
        $this->assertSame('payload', $handler->read('abc123'));
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
    public function it_rejects_a_table_name_that_is_not_an_identifier(): void
    {
        $this->expectException(SessionHandlerException::class);

        new MySQLHandler(['pdo' => $this->pdo(), 'table' => 'sessions`; DROP TABLE users; --']);
    }

    #[Test]
    public function it_rejects_a_missing_pdo_handle(): void
    {
        $this->expectException(SessionHandlerException::class);

        new MySQLHandler([]);
    }

    #[Test]
    public function setup_does_nothing_unless_install_is_enabled(): void
    {
        $this->pdo()->exec('DROP TABLE `' . $this->table . '`');

        $this->handler(['install' => false])->setup();

        $found = $this->pdo()
            ->query('SHOW TABLES LIKE ' . $this->pdo()->quote($this->table))
            ->fetchColumn();

        $this->assertFalse($found);
    }
}

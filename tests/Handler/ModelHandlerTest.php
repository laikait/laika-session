<?php

declare(strict_types=1);

namespace Laika\Session\Tests\Handler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Handler\ModelHandler;

/**
 * Exercises the laika-model driver. laika-model is an optional dependency, so
 * the whole case skips when it is absent.
 *
 * Point it at a database with LAIKA_SESSION_MODEL_CONNECTION; the connection
 * itself is configured by laika-model, not by this package.
 */
class ModelHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Laika\Model\Model::class)) {
            $this->markTestSkipped('laikait/laika-model is not installed.');
        }

        if (getenv('LAIKA_SESSION_MODEL_CONNECTION') === false) {
            $this->markTestSkipped('Set LAIKA_SESSION_MODEL_CONNECTION to run the model driver tests.');
        }
    }

    protected function handler(array $params = []): ModelHandler
    {
        return new ModelHandler(array_merge(
            ['connection' => getenv('LAIKA_SESSION_MODEL_CONNECTION'), 'install' => true],
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

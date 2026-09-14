<?php

declare(strict_types=1);

namespace Laika\Session\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\SessionConfig;
use Laika\Session\Session;
use Laika\Session\SessionManager;

/**
 * Session and SessionManager both hold static state, so setUp() resets both and
 * tearDown() closes any session the case left open.
 */
class SessionTest extends TestCase
{
    protected string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laika-session-test-' . bin2hex(random_bytes(4));
        mkdir($this->path);

        SessionConfig::reset();
        SessionManager::reset();
        SessionConfig::file(['path' => $this->path, 'prefix' => 'TS']);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->path);
    }

    #[Test]
    public function it_sets_and_gets_a_single_value(): void
    {
        Session::set('user_id', 42);

        $this->assertSame(42, Session::get('user_id'));
        $this->assertTrue(Session::has('user_id'));
    }

    #[Test]
    public function a_value_is_confined_to_its_scope(): void
    {
        Session::scope('AUTH')->set('token', 'abc123');

        $this->assertSame('abc123', Session::scope('AUTH')->get('token'));
        $this->assertNull(Session::get('token'));
    }

    #[Test]
    public function the_facade_uses_the_app_scope(): void
    {
        Session::set('a', 1);

        $this->assertSame(1, Session::scope('APP')->get('a'));
        $this->assertSame('APP', Session::scope()->name());
    }

    #[Test]
    public function get_returns_the_default_when_the_key_is_missing(): void
    {
        $this->assertSame('fallback', Session::get('nope', 'fallback'));
        $this->assertSame('fallback', Session::scope('AUTH')->get('nope', 'fallback'));
    }

    #[Test]
    public function scopes_do_not_collide(): void
    {
        Session::scope('USER')->set('id', 42);
        Session::scope('CART')->set('id', 99);

        $this->assertSame(42, Session::scope('USER')->get('id'));
        $this->assertSame(99, Session::scope('CART')->get('id'));
    }

    #[Test]
    public function scope_names_are_trimmed_and_uppercased(): void
    {
        Session::scope('auth')->set('token', 'abc123');

        $this->assertSame('abc123', Session::scope(' Auth ')->get('token'));
        $this->assertSame('abc123', Session::scope('AUTH')->get('token'));
        $this->assertSame('AUTH', Session::scope('auth')->name());
    }

    #[Test]
    public function an_empty_scope_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Session::scope('   ');
    }

    #[Test]
    public function pop_removes_only_the_named_key(): void
    {
        Session::set('a', 1);
        Session::set('b', 2);
        Session::pop('a');

        $this->assertFalse(Session::has('a'));
        $this->assertTrue(Session::has('b'));
    }

    #[Test]
    public function pop_removes_a_key_holding_null(): void
    {
        Session::set('a', null);
        Session::pop('a');

        $this->assertArrayNotHasKey('a', Session::all());
    }

    #[Test]
    public function pop_starts_the_session_when_called_first(): void
    {
        Session::scope('AUTH')->pop('token');

        $this->assertTrue(SessionManager::isStarted());
    }

    #[Test]
    public function purge_clears_one_scope(): void
    {
        Session::set('a', 1);
        Session::scope('AUTH')->set('b', 2);

        Session::purge();

        $this->assertFalse(Session::has('a'));
        $this->assertTrue(Session::scope('AUTH')->has('b'));
    }

    #[Test]
    public function purge_starts_the_session_when_called_first(): void
    {
        // purge() was the only mutator that skipped SessionManager::start(),
        // so as the first call of a request it silently did nothing.
        Session::purge();

        $this->assertTrue(SessionManager::isStarted());
    }

    #[Test]
    public function all_returns_one_scope(): void
    {
        Session::set('a', 1);
        Session::scope('AUTH')->set('b', 2);

        $this->assertSame(['a' => 1], Session::all());
        $this->assertSame(['a' => 1], Session::scope('APP')->all());
        $this->assertSame(['b' => 2], Session::scope('AUTH')->all());
        $this->assertSame([], Session::scope('NOPE')->all());
    }

    #[Test]
    public function id_and_name_return_strings(): void
    {
        Session::set('a', 1);

        $this->assertNotSame('', Session::id());
        $this->assertSame('LFSESS', Session::name());
    }

    #[Test]
    public function data_survives_a_write_and_reopen(): void
    {
        Session::set('user_id', 42);
        Session::scope('AUTH')->set('token', 'abc123');
        $id = Session::id();

        session_write_close();
        SessionManager::reset();

        session_id($id);
        SessionManager::start();

        $this->assertSame(42, Session::get('user_id'));
        $this->assertSame('abc123', Session::scope('AUTH')->get('token'));
    }
}

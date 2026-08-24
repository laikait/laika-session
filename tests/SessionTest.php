<?php

declare(strict_types=1);

namespace Laika\Session\Tests;

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
    public function a_value_is_scoped_to_its_namespace(): void
    {
        Session::set('token', 'abc123', 'AUTH');

        $this->assertSame('abc123', Session::get('token', null, 'AUTH'));
        $this->assertNull(Session::get('token'));
    }

    #[Test]
    public function get_returns_the_default_when_the_key_is_missing(): void
    {
        $this->assertSame('fallback', Session::get('nope', 'fallback'));
    }

    #[Test]
    public function namespaces_do_not_collide(): void
    {
        Session::set('id', 42, 'USER');
        Session::set('id', 99, 'CART');

        $this->assertSame(42, Session::get('id', null, 'USER'));
        $this->assertSame(99, Session::get('id', null, 'CART'));
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
    public function purge_clears_one_namespace(): void
    {
        Session::set('a', 1, 'APP');
        Session::set('b', 2, 'AUTH');

        Session::purge('APP');

        $this->assertFalse(Session::has('a'));
        $this->assertTrue(Session::has('b', 'AUTH'));
    }

    #[Test]
    public function purge_starts_the_session_when_called_first(): void
    {
        // purge() was the only mutator that skipped SessionManager::start(),
        // so as the first call of a request it silently did nothing.
        Session::purge('APP');

        $this->assertTrue(SessionManager::isStarted());
    }

    #[Test]
    public function get_for_returns_one_namespace(): void
    {
        Session::set('a', 1, 'APP');
        Session::set('b', 2, 'AUTH');

        $this->assertSame(['a' => 1], Session::getFor('APP'));
        $this->assertSame(['a' => 1], Session::getFor());
        $this->assertSame(['b' => 2], Session::getFor('AUTH'));
        $this->assertSame([], Session::getFor('NOPE'));
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
        $id = Session::id();

        session_write_close();
        SessionManager::reset();

        session_id($id);
        SessionManager::start();

        $this->assertSame(42, Session::get('user_id'));
    }
}

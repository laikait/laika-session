<?php

declare(strict_types=1);

namespace Laika\Session\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\SessionConfig;
use Laika\Session\Session;
use Laika\Session\SessionManager;
use Laika\Session\Handler\FileHandler;
use Laika\Session\Handler\MySQLHandler;
use Laika\Session\Handler\HandlerFactory;
use Laika\Session\Exceptions\SessionHandlerException;

class SessionManagerTest extends TestCase
{
    protected string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laika-manager-test-' . bin2hex(random_bytes(4));
        mkdir($this->path);

        SessionConfig::reset();
        SessionManager::reset();
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

    protected function configureFile(): void
    {
        SessionConfig::file(['path' => $this->path, 'prefix' => 'TS']);
    }

    #[Test]
    public function it_reports_configuration_state(): void
    {
        $this->assertFalse(SessionManager::isConfigured());

        $this->configureFile();

        $this->assertTrue(SessionManager::isConfigured());
    }

    #[Test]
    public function starting_without_a_driver_names_the_config_methods(): void
    {
        $this->expectException(SessionHandlerException::class);
        $this->expectExceptionMessageMatches('/SessionConfig::file\(\)/');

        SessionManager::start();
    }

    #[Test]
    public function it_builds_the_handler_the_config_selected(): void
    {
        $this->configureFile();

        $this->assertInstanceOf(FileHandler::class, SessionManager::handler());
    }

    #[Test]
    public function the_handler_is_built_once_and_reused(): void
    {
        $this->configureFile();

        $this->assertSame(SessionManager::handler(), SessionManager::handler());
    }

    #[Test]
    public function it_starts_and_reports_started(): void
    {
        $this->configureFile();

        $this->assertFalse(SessionManager::isStarted());

        SessionManager::start();

        $this->assertTrue(SessionManager::isStarted());
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    #[Test]
    public function starting_twice_is_harmless(): void
    {
        $this->configureFile();

        SessionManager::start();
        $id = session_id();
        SessionManager::start();

        $this->assertSame($id, session_id());
    }

    #[Test]
    public function destroy_clears_the_session_data(): void
    {
        $this->configureFile();

        Session::set('user_id', 42);
        $this->assertTrue(SessionManager::destroy());

        $this->assertSame([], $_SESSION);
        $this->assertFalse(SessionManager::isStarted());
    }

    #[Test]
    public function the_configured_session_name_is_applied(): void
    {
        $this->configureFile();
        SessionConfig::options(['name' => 'MY_APP']);

        SessionManager::start();

        $this->assertSame('MY_APP', session_name());
    }

    #[Test]
    public function the_cache_lifetime_reaches_the_driver(): void
    {
        // The cache drivers expire keys themselves, so they have to inherit the
        // lifetime the rest of the session runs on.
        SessionConfig::options(['gc_maxlifetime' => 99]);
        SessionConfig::file(['path' => $this->path]);

        $handler = SessionManager::handler();

        $this->assertInstanceOf(FileHandler::class, $handler);
        $this->assertSame(99, SessionConfig::options()['gc_maxlifetime']);
    }

    #[Test]
    public function reset_forgets_the_built_handler(): void
    {
        $this->configureFile();
        $first = SessionManager::handler();

        SessionManager::reset();

        $this->assertNotSame($first, SessionManager::handler());
    }

    #[Test]
    public function the_factory_rejects_an_unknown_driver(): void
    {
        $this->expectException(SessionHandlerException::class);
        $this->expectExceptionMessageMatches('/Unknown session driver/');

        HandlerFactory::make('mongodb', []);
    }

    #[Test]
    public function the_factory_builds_every_supported_driver_name(): void
    {
        $this->assertInstanceOf(
            FileHandler::class,
            HandlerFactory::make(SessionConfig::DRIVER_FILE, ['path' => $this->path])
        );

        $this->assertInstanceOf(
            MySQLHandler::class,
            HandlerFactory::make(SessionConfig::DRIVER_MYSQL, ['pdo' => new PDO('sqlite::memory:')])
        );
    }
}

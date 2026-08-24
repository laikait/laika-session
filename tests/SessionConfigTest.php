<?php

declare(strict_types=1);

namespace Laika\Session\Tests;

use PDO;
use Laika\Model\Model;
use Laika\Session\SessionConfig;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laika\Session\Exceptions\SessionHandlerException;

class SessionConfigTest extends TestCase
{
    protected function setUp(): void
    {
        SessionConfig::reset();
    }

    protected function tearDown(): void
    {
        SessionConfig::reset();
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    }

    #[Test]
    public function it_starts_unconfigured(): void
    {
        $this->assertFalse(SessionConfig::isConfigured());
        $this->assertNull(SessionConfig::driver());
        $this->assertSame([], SessionConfig::params());
    }

    #[Test]
    public function file_driver_sets_driver_and_defaults_the_prefix(): void
    {
        SessionConfig::file(['path' => sys_get_temp_dir()]);

        $this->assertTrue(SessionConfig::isConfigured());
        $this->assertSame(SessionConfig::DRIVER_FILE, SessionConfig::driver());
        $this->assertSame('LK', SessionConfig::params()['prefix']);
        $this->assertSame(sys_get_temp_dir(), SessionConfig::params()['path']);
    }

    #[Test]
    public function mysql_driver_carries_the_pdo_handle_and_table_default(): void
    {
        $pdo = new PDO('sqlite::memory:');
        SessionConfig::mysql($pdo);

        $this->assertSame(SessionConfig::DRIVER_MYSQL, SessionConfig::driver());
        $this->assertSame($pdo, SessionConfig::params()['pdo']);
        $this->assertSame('sessions', SessionConfig::params()['table']);
        $this->assertFalse(SessionConfig::params()['install']);
    }

    #[Test]
    public function a_second_driver_call_switches_drivers_and_replaces_params(): void
    {
        SessionConfig::file(['path' => sys_get_temp_dir(), 'prefix' => 'AA']);
        SessionConfig::mysql(new PDO('sqlite::memory:'), ['table' => 'other']);

        $this->assertSame(SessionConfig::DRIVER_MYSQL, SessionConfig::driver());
        $this->assertSame('other', SessionConfig::params()['table']);

        // The file driver's params must not bleed into the new driver.
        $this->assertArrayNotHasKey('path', SessionConfig::params());
    }

    #[Test]
    public function options_merge_over_the_defaults_instead_of_replacing_them(): void
    {
        $options = SessionConfig::options(['name' => 'MY_APP']);

        $this->assertSame('MY_APP', $options['name']);
        // Untouched defaults survive a partial call.
        $this->assertSame(1440, $options['gc_maxlifetime']);
        $this->assertTrue($options['use_strict_mode']);
    }

    #[Test]
    public function cookies_merge_over_the_defaults_instead_of_replacing_them(): void
    {
        $cookies = SessionConfig::cookies(['domain' => '.example.com']);

        $this->assertSame('.example.com', $cookies['domain']);
        $this->assertSame('/', $cookies['path']);
        $this->assertTrue($cookies['httponly']);
        $this->assertSame('Strict', $cookies['samesite']);
    }

    #[Test]
    public function successive_option_calls_accumulate(): void
    {
        SessionConfig::options(['name' => 'ONE']);
        $options = SessionConfig::options(['gc_maxlifetime' => 60]);

        $this->assertSame('ONE', $options['name']);
        $this->assertSame(60, $options['gc_maxlifetime']);
    }

    #[Test]
    public function secure_cookie_is_off_on_plain_http(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';

        // Hardcoding secure=true means the browser never returns the cookie
        // over plain HTTP and every request silently gets a fresh session.
        $this->assertFalse(SessionConfig::cookies()['secure']);
    }

    #[Test]
    public function secure_cookie_is_on_under_tls(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(SessionConfig::cookies()['secure']);
    }

    #[Test]
    public function secure_cookie_can_still_be_forced(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

        $this->assertTrue(SessionConfig::cookies(['secure' => true])['secure']);
    }

    #[Test]
    public function model_driver_reports_the_missing_package_by_name(): void
    {
        if (class_exists(Model::class)) {
            $this->markTestSkipped('laika-model is installed, so the missing-package path cannot be exercised.');
        }

        $this->expectException(SessionHandlerException::class);
        $this->expectExceptionMessageMatches('/laikait\/laika-model/');

        SessionConfig::model();
    }

    #[Test]
    public function reset_clears_everything(): void
    {
        SessionConfig::file(['path' => sys_get_temp_dir()]);
        SessionConfig::options(['name' => 'MY_APP']);

        SessionConfig::reset();

        $this->assertFalse(SessionConfig::isConfigured());
        $this->assertNull(SessionConfig::driver());
        $this->assertSame('LFSESS', SessionConfig::options()['name']);
    }
}

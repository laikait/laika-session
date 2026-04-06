<?php
/**
 * Laika Database Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session;

use Laika\Session\Exceptions\SessionHandlerException;
use Laika\Session\Interface\SessionDriverInterface;
use Laika\Session\Handler\MemcachedSessionHandler;
use Laika\Session\Handler\RedisSessionHandler;
use Laika\Session\Handler\FileSessionHandler;
use Laika\Session\Handler\PdoSessionHandler;
use Memcached;
use Redis;
use PDO;

class SessionManager
{
    // Session Handler
    /**
     * @var SessionDriverInterface $handler
     */
    protected static SessionDriverInterface $handler;

    // Session Active Status
    /**
     * @var bool $started. Default is false
     */
    protected static bool $started = false;

    // Session Options To Start
    /**
     * @var array<string,mixed> $options. Session Start Options
     */
    protected static array $options;

    /**
     * Session Cookie Parameters
     * @var array<string,mixed> $cookies. Session Cookie Parameters
     */
    protected static array $cookies;

    /**
     * Session Handler Config
     * @param null|PDO|Redis|Memcached $instance
     * @param array $args Example: ['path' => '/session_path/', 'prefix' => 'LK']
     * File Session: Ignore This Parameter.
     * PDO Session: PDO Object or ['driver'=>'pdo'] and dsn,username,password Keys are Required
     * Redis Session: Redis Object or ['driver'=>'redis']. host,port,timeout,prefix,password Keys are Optional
     * Memcached Session: Memcached Object or ['driver'=>'memcached']. host,port,timeout,prefix Keys are Optional
     * @return void
     */
    public static function config(null|PDO|Redis|Memcached $instance = null, array $args = ['prefix' => 'LK']): void
    {
        self::$handler = match (true) {
            $instance instanceof PDO        =>  new PdoSessionHandler($instance),
            $instance instanceof Redis      =>  new RedisSessionHandler($instance, $args),
            $instance instanceof Memcached  =>  new MemcachedSessionHandler($instance, $args),
            default                         =>  new FileSessionHandler($args)
        };

        // Default Session Options
        self::$options = self::defaultOptions();
        // Default Session Cookies
        self::$cookies = self::defaultCookies();
        return;
    }

    /**
     * Set Session Options
     * @param array $options. Example ['name'=>'PHPSESSID'] and any other session options
     */
    public static function setOptions(array $options): void
    {
        if (!isset(self::$options)) {
            throw new SessionHandlerException('Call SessionManager::config() Before Set Options.');
        }
        self::$options = array_merge(self::$options, $options);
        return;
    }

    // Set Session Cookies
    /**
     * @param array $cookies. Example ['path'=>'/'] and any other session cookies
     */
    public static function setCookies(array $cookies): void
    {
        if (!isset(self::$options)) {
            throw new SessionHandlerException('Call SessionManager::config() Before Set Cookies.');
        }
        self::$cookies = array_merge(self::$cookies, $cookies);
        return;
    }

    // Start Session
    public static function start(): void
    {
        if (!isset(self::$handler)) {
            throw new SessionHandlerException('Call SessionManager::config() before starting the session.');
        }
        if (!self::$started && (session_status() !== PHP_SESSION_ACTIVE)) {
            self::$handler->setup();
            session_set_save_handler(self::$handler, true);

            // Session Cookies
            session_set_cookie_params(self::$cookies);

            // Session Start
            session_start(self::$options);
            self::$started = true;
        }
        return;
    }

    // Session End
    public static function end(): bool
    {
        self::start();
        session_unset();
        self::$started = false;
        return session_destroy();
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * @return array<string,mixed> Default Session Options
     */
    protected static function defaultOptions(): array
    {
        return [
            'name'              =>  'LAIKA',
            'use_only_cookies'	=>	true,
            'use_strict_mode'	=>	true,
            'gc_probability'	=>	1,
            'gc_divisor'		=>	100,
            'gc_maxlifetime'	=>	1440
        ];
    }

    /**
     * @return array<string,mixed> Default Session Cookies
     */
    protected static function defaultCookies(): array
    {
        return [
            "path"      =>  '/',
            "secure"    =>  true,
            "httponly"  =>  true,
            "samesite"  =>  "Strict"
        ];
    }
}

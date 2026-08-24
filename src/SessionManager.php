<?php
/**
 * Laika Session
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Laika\Session;

use Laika\Session\Handler\HandlerFactory;
use Laika\Session\Contracts\SessionDriverInterface;
use Laika\Session\Exceptions\SessionHandlerException;

/**
 * Session Manager
 *
 * Reads the driver SessionConfig selected, builds it, and runs the session lifecycle.
 * Configuration lives in SessionConfig; this class only starts and stops.
 */
class SessionManager
{
    /** @var ?SessionDriverInterface $handler Built lazily on the first start(). */
    protected static ?SessionDriverInterface $handler = null;

    /** @var bool $started Default is false */
    protected static bool $started = false;

    /** @var bool $prepared Whether the handler's setup() has already run this process. */
    protected static bool $prepared = false;

    /**
     * Start the session. Safe to call repeatedly; the session starts once.
     * @return void
     */
    public static function start(): void
    {
        if (static::$started || session_status() === PHP_SESSION_ACTIVE) {
            static::$started = true;
            return;
        }

        $handler = static::handler();

        // Creating the table is a one-off, not a per-request round trip, and it
        // needs DDL privileges the runtime user should not normally hold.
        if (!static::$prepared) {
            $handler->setup();
            static::$prepared = true;
        }

        session_set_save_handler($handler, true);
        session_set_cookie_params(SessionConfig::cookies());

        // A failed start must not be recorded as a started session, or every
        // later call assumes a session that is not there.
        static::$started = session_start(SessionConfig::options());
    }

    /**
     * Whether a driver has been selected.
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return SessionConfig::isConfigured();
    }

    /**
     * Whether the session is currently active.
     * @return bool
     */
    public static function isStarted(): bool
    {
        return static::$started && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * The active driver, built on demand.
     * @return SessionDriverInterface
     */
    public static function handler(): SessionDriverInterface
    {
        if (static::$handler !== null) {
            return static::$handler;
        }

        $driver = SessionConfig::driver();
        if ($driver === null) {
            throw new SessionHandlerException(
                'No session driver configured. Call SessionConfig::file(), SessionConfig::redis(), SessionConfig::memcached(), '
                . 'SessionConfig::mysql() or SessionConfig::model() before starting the session.'
            );
        }

        $params = SessionConfig::params();

        // The cache drivers expire keys themselves, so they need the lifetime
        // that the rest of the session already runs on.
        $params += ['lifetime' => (int) (SessionConfig::options()['gc_maxlifetime'] ?? 1440)];

        return static::$handler = HandlerFactory::make($driver, $params);
    }

    /**
     * Destroy the session and clear its cookie.
     * @return bool
     */
    public static function destroy(): bool
    {
        static::start();

        $_SESSION = [];
        session_unset();

        // session_destroy() drops the server-side data but leaves the cookie in
        // the browser, so the client keeps presenting a dead session id.
        static::clearCookie();

        $destroyed       = session_destroy();
        static::$started = false;

        return $destroyed;
    }

    /**
     * Forget the built handler and the started flag.
     * Intended for tests, which need to rebuild static state between cases.
     * @return void
     */
    public static function reset(): void
    {
        static::$handler  = null;
        static::$started  = false;
        static::$prepared = false;
    }

    ########################################################################
    /*--------------------------- INTERNAL API ---------------------------*/
    ########################################################################

    /**
     * Expire the session cookie in the browser.
     * @return void
     */
    protected static function clearCookie(): void
    {
        if (headers_sent() || !ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();
        $params['expires'] = time() - 42000;
        unset($params['lifetime']);

        setcookie(session_name(), '', $params);
    }
}

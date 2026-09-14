# Getting Started

## The Three Classes

| Class | Job |
|---|---|
| `Laika\Session\SessionConfig` | Selects the driver and holds session options and cookie parameters |
| `Laika\Session\Session` | Reads and writes session data |
| `Laika\Session\SessionManager` | Starts and destroys the session |

You configure `SessionConfig` once, then use `Session` everywhere else. You rarely touch `SessionManager` directly.

## Requirements

- PHP `>= 8.1`
- Only what your driver needs:

| Driver | Needs |
|---|---|
| `file` | Nothing |
| `mysql` | `ext-pdo` with the MySQL driver (`pdo_mysql`) |
| `model` | `laikait/laika-model` |
| `redis` | `ext-redis` |
| `memcached` | `ext-memcached` |

## Installation

```bash
composer require laikait/laika-session
```

## Your First Session

Select a driver once during bootstrap, before any session read or write:

```php
use Laika\Session\SessionConfig;
use Laika\Session\Session;

SessionConfig::file();

Session::set('user_id', 42);
echo Session::get('user_id'); // 42
```

Nothing happens at configuration time. The session starts on the first `Session::` call. If no driver was selected by then, that call throws `SessionHandlerException` with *"No session driver configured."*

Select the driver before any output is sent. Starting a session writes a cookie header, and PHP can't send headers once output has started.

## Inside the Laika Framework

The framework wraps driver selection in the `Init` service. Its method names mirror `SessionConfig`, and it builds the clients from `lf-config/`, so no credentials pass through your bootstrap code:

```php
// lf-hooks/session.php
use Laika\Service\Init;

Init::file(['path' => APP_PATH . '/lf-storage/sessions']);
// or: Init::model('default'), Init::mysql('default'), Init::redis(), Init::memcached()
```

| `Init` call | Same as |
|---|---|
| `Init::file($params)` | `SessionConfig::file($params)` |
| `Init::model($name, $install)` | `SessionConfig::model(['connection' => $name, 'install' => $install])`, after registering the connection |
| `Init::mysql($name, $params)` | `SessionConfig::mysql(<PDO for $name>, $params)` |
| `Init::redis($params)` | `SessionConfig::redis(<client from lf-config/redis.php>, $params)` |
| `Init::memcached($params)` | `SessionConfig::memcached(<client from lf-config/memcached.php>, $params)` |

`Init` also marks the session cookie `Secure` whenever the framework detects HTTPS, including behind a proxy listed in `trusted_proxies`. See [Configuration](03_configuration.md#the-secure-flag).

## Next

- Pick a driver: [Drivers](02_drivers.md)
- Tune the cookie and lifetime: [Configuration](03_configuration.md)
- Everything the facade can do: [Session API](04_session-api.md)

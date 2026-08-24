# Laika Session

A PHP session package for the Laika Framework with **file**, **redis**, **memcached**, **mysql**, and **Laika Model** drivers behind a clean static facade.

## Requirements

- PHP `>= 8.1`
- `ext-pdo` — bundled with PHP, required by the `mysql` driver
- `ext-redis` — only for the `redis` driver
- `ext-memcached` — only for the `memcached` driver
- `laikait/laika-model` — only for the `model` driver

The `mysql` driver talks to PDO directly, so a database-backed session needs nothing beyond `ext-pdo`. Use the `model` driver when you are inside the framework and want sessions to go through `SessionModel` / `SessionSchema`.

## Installation

```bash
composer require laikait/laika-session
```

---

## Quick Start

Choose a driver once at application bootstrap, before any session read or write. Everything else is lazy — the session starts on the first `Session::` call.

```php
use Laika\Session\SessionConfig;
use Laika\Session\Session;

SessionConfig::file(); // file driver, sensible defaults

Session::set('user_id', 42);
echo Session::get('user_id'); // 42
```

---

## Drivers

Pick exactly one. Calling a second driver method switches drivers and replaces its params.

### File

Sessions as files on disk. No dependencies. Suitable for single-server deployments.

```php
SessionConfig::file([
    'path'   => '/var/www/storage/sessions', // optional, defaults to session_save_path()
    'prefix' => 'LK',                        // optional, default 'LK'
]);
```

Files are created `0600` and locked from read to write, so concurrent requests on one session cannot clobber each other.

### MySQL

Raw PDO. Pass a connected instance; this package never handles credentials.

```php
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4',
    'username',
    'password',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

SessionConfig::mysql($pdo, [
    'table'   => 'sessions', // optional, default 'sessions'
    'install' => false,      // optional, default false — see below
]);
```

**Table schema:**

```sql
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) NOT NULL,
    `data`          BLOB NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_last_activity` (`last_activity`)
);
```

Set `'install' => true` to have the table created on first use. Leave it `false` in production — creating tables at request time costs a round trip on every page load and needs DDL privileges your runtime user should not hold. Create the table once from a migration instead.

### Laika Model

Uses `Laika\Session\Model\SessionModel` and `Laika\Session\Schema\SessionSchema`. Requires `laikait/laika-model`; the connection itself is configured there.

```php
SessionConfig::model([
    'connection' => 'default', // optional, default 'default'
    'install'    => false,     // optional, default false
]);
```

Shares the same table layout as the `mysql` driver, so you can switch between the two without a migration.

### Redis

Pass a connected and authenticated client.

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('your-password'); // omit if no auth

SessionConfig::redis($redis, [
    'prefix'   => 'LK',   // optional, default 'LK'
    'lifetime' => 1440,   // optional, seconds — defaults to gc_maxlifetime
]);
```

### Memcached

Pass a configured client.

```php
$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

SessionConfig::memcached($memcached, [
    'prefix'   => 'LK',   // optional, default 'LK'
    'lifetime' => 1440,   // optional, seconds — defaults to gc_maxlifetime
]);
```

Redis and Memcached expire keys themselves, so garbage collection is a no-op for both.

---

## Configuration

### Session Options

Merges over the defaults, so a partial call leaves everything else intact.

```php
SessionConfig::options([
    'name'           => 'MY_APP',   // session cookie name, default 'LFSESS'
    'gc_maxlifetime' => 3600,       // session lifetime in seconds, default 1440
    'gc_probability' => 1,
    'gc_divisor'     => 100,
]);
```

| Option             | Default   |
|--------------------|-----------|
| `name`             | `LFSESS`  |
| `use_only_cookies` | `true`    |
| `use_strict_mode`  | `true`    |
| `gc_probability`   | `1`       |
| `gc_divisor`       | `100`     |
| `gc_maxlifetime`   | `1440`    |

### Cookie Parameters

```php
SessionConfig::cookies([
    'path'     => '/',
    'domain'   => '.example.com',
    'secure'   => true,     // HTTPS only
    'httponly' => true,     // no JS access — default true
    'samesite' => 'Strict', // default 'Strict'
]);
```

| Parameter  | Default                  |
|------------|--------------------------|
| `path`     | `/`                      |
| `secure`   | *follows the connection* |
| `httponly` | `true`                   |
| `samesite` | `Strict`                 |

> **`secure` follows the connection.** It is `true` over HTTPS and `false` over plain HTTP. Hardcoding it to `true` on a plain-HTTP development machine means the browser never sends the cookie back, so every request silently gets a brand new session — a confusing failure with no error to go on. Force it with `SessionConfig::cookies(['secure' => true])` if you terminate TLS somewhere this cannot detect.

---

## Session API

All methods are static and available on the `Session` facade. Each one starts the session on demand.

### `Session::set()`

Store one or many values. Data is namespaced under a `$for` key (default `APP`).

```php
// Single value
Session::set('user_id', 42);

// Custom namespace
Session::set('token', 'abc123', 'AUTH');

```

### `Session::get()`

Retrieve a value. Returns `$default` (or `null`) when the key is missing.

**Signature:** `get(string $key, mixed $default = null, string $for = 'APP')` — the namespace is the *third* argument, not the second.

```php
$userId = Session::get('user_id');                // from 'APP'
$token  = Session::get('token', null, 'AUTH');    // from 'AUTH'
$role   = Session::get('role', 'guest');          // with a default
```

### `Session::has()`

```php
if (Session::has('user_id')) {
    // logged in
}
```

### `Session::pop()`

Remove a key if it exists.

```php
Session::pop('flash_message');
Session::pop('token', 'AUTH');
```

### `Session::purge()`

Clear an entire namespace.

```php
Session::purge();        // clears 'APP'
Session::purge('AUTH');  // clears 'AUTH'
```

### `Session::getFor()`

Return every key in one namespace.

```php
$app  = Session::getFor();        // the 'APP' namespace
$auth = Session::getFor('AUTH');  // a named namespace
```

Returns an empty array when the namespace holds nothing.

### `Session::regenerate()`

Regenerate the session ID. Pass `false` to keep the old session data.

```php
Session::regenerate();      // regenerate and delete the old session
Session::regenerate(false); // regenerate but keep the old data
```

### `Session::id()` / `Session::name()`

```php
$id   = Session::id();
$name = Session::name();
```

### `Session::destroy()`

Destroy the session, its data, and its cookie.

```php
Session::destroy();
```

---

## Namespacing

Sessions are stored under a namespace key (`$for`) within `$_SESSION`, which prevents key collisions when several parts of an application share one session.

```php
Session::set('id', 42, 'USER');
Session::set('id', 99, 'CART');

Session::get('id', null, 'USER'); // 42
Session::get('id', null, 'CART'); // 99
```

The default namespace is `APP`.

---

## Manual Control

`SessionManager` runs the lifecycle; you rarely need it directly, since every `Session::` call starts the session on demand.

```php
use Laika\Session\SessionManager;

SessionManager::isConfigured(); // has a driver been selected?
SessionManager::isStarted();    // is the session active?
SessionManager::start();        // start explicitly
SessionManager::handler();      // the active driver instance
SessionManager::destroy();      // destroy the session and its cookie
```

---

## Full Bootstrap Example

```php
use Laika\Session\SessionConfig;
use Laika\Session\Session;

// 1. Choose a driver
$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

SessionConfig::mysql($pdo);

// 2. Customise (optional)
SessionConfig::options(['name' => 'MY_APP', 'gc_maxlifetime' => 7200]);
SessionConfig::cookies(['domain' => '.example.com']);

// 3. Use the Session facade anywhere
Session::set('user_id', 1);

if (Session::has('user_id')) {
    $id = Session::get('user_id');
    Session::regenerate(); // rotate the session ID on privilege change
}

// On logout
Session::destroy();
```

---

## Upgrading from v4

`v5.0.0` replaces the two config methods with the `Config` entry point.

| v4                                        | v5                                          |
|-------------------------------------------|---------------------------------------------|
| `SessionManager::fileSessionConfig([...])` | `SessionConfig::file([...])`                       |
| `SessionManager::dbSessionConfig('default')` | `SessionConfig::model(['connection' => 'default'])` |
| `SessionManager::setOptions([...])`        | `SessionConfig::options([...])`                    |
| `SessionManager::setCookies([...])`        | `SessionConfig::cookies([...])`                    |
| `SessionManager::isConfiguarded()`         | `SessionManager::isConfigured()`            |
| `Laika\Session\Interface\SessionDriverInterface` | `Laika\Session\Contracts\SessionDriverInterface` |
| `Laika\Session\Handler\PDOHandler`         | `Laika\Session\Handler\ModelHandler`        |

Other changes worth knowing:

- **`Session::set()` with an array now works.** It previously wrote nothing at all.
- **Garbage collection no longer deletes live sessions.** The old database `gc()` ignored `$maxlifetime` and matched every row.
- **Cookie `secure` follows the connection** instead of being hardcoded `true`.
- **The table is no longer created on every request.** Pass `'install' => true` to opt in.
- A custom driver must now also implement `SessionUpdateTimestampHandlerInterface` (`validateId()` and `updateTimestamp()`), which is what keeps an actively browsing user's session from being garbage collected.

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Driver tests skip themselves when their backend is unreachable. Point them at real servers with:

```bash
LAIKA_SESSION_DSN, LAIKA_SESSION_USER, LAIKA_SESSION_PASS       # mysql
LAIKA_SESSION_REDIS_HOST, LAIKA_SESSION_REDIS_PORT              # redis
LAIKA_SESSION_MEMCACHED_HOST, LAIKA_SESSION_MEMCACHED_PORT      # memcached
LAIKA_SESSION_MODEL_CONNECTION                                  # model
```

Compatibility across PHP 8.1–8.5:

```bash
composer compat
```

---

## License

MIT — see [LICENSE](LICENSE) for full terms.

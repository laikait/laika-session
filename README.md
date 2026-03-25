# Laika Session

A PHP session package for the Laika Framework supporting File, PDO, Redis, and Memcached backends via a clean static facade.

## Requirements

- PHP `>= 8.1`
- `ext-pdo` — for PDO driver
- `ext-redis` — for Redis driver
- `ext-memcached` — for Memcached driver

## Installation

```bash
composer require laikait/laika-session
```

---

## Quick Start

Call `SessionManager::config()` once at your application bootstrap, before any session reads or writes.

```php
use Laika\Session\SessionManager;
use Laika\Session\Session;

// File driver (default — no instance required)
SessionManager::config();

// Write and read
Session::set('user_id', 42);
echo Session::get('user_id'); // 42
```

---

## Drivers

### File

Stores sessions as files on disk. No dependencies. Suitable for single-server deployments.

```php
SessionManager::config(null, [
    'path'   => '/var/www/storage/sessions', // optional, defaults to session_save_path()
    'prefix' => 'LK',                        // optional, default 'LK'
]);
```

### PDO (MySQL)

Stores sessions in a database table. Pass a pre-configured `PDO` instance. The `sessions` table is created automatically on first use.

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

SessionManager::config($pdo);
```

**Auto-created table schema:**

```sql
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) PRIMARY KEY,
    `data`          BLOB,
    `last_activity` INT
);
```

### Redis

Pass a connected and authenticated `Redis` instance.

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('your-password'); // omit if no auth

SessionManager::config($redis, [
    'prefix'         => 'LK',   // optional, default 'LK'
    'gc_maxlifetime' => 1440,   // optional, seconds — defaults to session.gc_maxlifetime ini
]);
```

### Memcached

Pass a configured `Memcached` instance.

```php
$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

SessionManager::config($memcached, [
    'prefix'         => 'LK',   // optional, default 'LK'
    'gc_maxlifetime' => 1440,   // optional, seconds — defaults to session.gc_maxlifetime ini
]);
```

---

## Configuration

### Session Options

Override PHP session options after calling `config()`:

```php
SessionManager::setOptions([
    'name'           => 'MY_APP',   // session cookie name, default 'LAIKA'
    'gc_maxlifetime' => 3600,       // session lifetime in seconds, default 1440
    'gc_probability' => 1,
    'gc_divisor'     => 100,
]);
```

### Cookie Parameters

```php
SessionManager::setCookies([
    'path'     => '/',
    'domain'   => '.example.com',
    'secure'   => true,     // HTTPS only — default true
    'httponly' => true,     // no JS access — default true
    'samesite' => 'Strict', // default 'Strict'
]);
```

**Default cookie parameters:**

| Parameter  | Default    |
|------------|------------|
| `path`     | `/`        |
| `secure`   | `true`     |
| `httponly` | `true`     |
| `samesite` | `Strict`   |

---

## Session API

All methods are static and available on the `Session` facade.

### `Session::set()`

Store one or multiple values. Data is namespaced under a `$for` key (default `APP`).

```php
// Single value
Session::set('user_id', 42);

// Multiple values at once
Session::set(['user_id' => 42, 'role' => 'admin']);

// Custom namespace
Session::set('token', 'abc123', 'AUTH');
```

### `Session::get()`

Retrieve a value. Returns `null` if not found.

```php
$userId = Session::get('user_id');        // from 'APP' namespace
$token  = Session::get('token', 'AUTH'); // from 'AUTH' namespace
```

### `Session::has()`

Check if a key exists.

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

### `Session::all()`

Return the entire `$_SESSION` superglobal.

```php
$all = Session::all();
```

### `Session::regenerate()`

Regenerate the session ID. Pass `false` to keep the old session data.

```php
Session::regenerate();        // regenerate and delete old session
Session::regenerate(false);   // regenerate but keep old session data
```

### `Session::id()`

Get the current session ID.

```php
$id = Session::id();
```

### `Session::name()`

Get the current session name.

```php
$name = Session::name();
```

### `Session::end()`

Destroy the session and all its data.

```php
Session::end();
```

---

## Namespacing

Sessions are stored under a namespace key (`$for`) within `$_SESSION`. This prevents key collisions when multiple parts of your application share a session.

```php
Session::set('id', 42, 'USER');
Session::set('id', 99, 'CART');

Session::get('id', 'USER'); // 42
Session::get('id', 'CART'); // 99
```

The default namespace is `APP`.

---

## Full Bootstrap Example

```php
use Laika\Session\SessionManager;
use Laika\Session\Session;

// 1. Configure driver
$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'user', 'pass');
SessionManager::config($pdo);

// 2. Customise options (optional)
SessionManager::setOptions(['name' => 'MY_APP', 'gc_maxlifetime' => 7200]);
SessionManager::setCookies(['domain' => '.example.com']);

// 3. Use the Session facade anywhere
Session::set('user_id', 1);

if (Session::has('user_id')) {
    $id = Session::get('user_id');
    Session::regenerate(); // rotate session ID on privilege change
}

// On logout
Session::end();
```

---

## License

MIT — see [LICENSE](LICENSE) for full terms.

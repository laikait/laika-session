# Session API

All methods are static. Each one starts the session on first use, so you never need to call `start()` yourself.

| Method | Returns | Does |
|---|---|---|
| `Session::set(string $key, mixed $value)` | `void` | Stores one value in the `APP` scope |
| `Session::get(string $key, mixed $default = null)` | `mixed` | Reads one value, or `$default` when missing |
| `Session::has(string $key)` | `bool` | Whether the key is set and not `null` |
| `Session::pop(string $key)` | `void` | Removes one key if present |
| `Session::purge()` | `void` | Removes every key in the `APP` scope |
| `Session::all()` | `array` | Every key in the `APP` scope, or `[]` |
| `Session::scope(string $name = 'APP')` | `Scope` | The same methods, for another scope. See [Scopes](#scopes). |
| `Session::regenerate(bool $deleteOldData = true)` | `bool` | Issues a new session ID |
| `Session::id()` | `string` | Current session ID, or `''` |
| `Session::name()` | `string` | Session cookie name |
| `Session::destroy()` | `bool` | Deletes the session, its data and its cookie |

```php
use Laika\Session\Session;

Session::set('user_id', 42);

$id   = Session::get('user_id');
$role = Session::get('role', 'guest'); // with a default

if (Session::has('user_id')) { /* ... */ }

Session::pop('flash');
Session::purge();

$everything = Session::all();
```

`set()` takes a single key. To store several values, call it once per key, or store an array as one value:

```php
Session::set('cart', ['sku-1' => 2, 'sku-2' => 1]);
```

`has()` uses `isset()`, so a key explicitly set to `null` reports `false`. `pop()` still removes such a key.

## Scopes

Every key lives in a scope, stored as `$_SESSION[<SCOPE>][$key]`, so unrelated parts of an application can't overwrite each other's keys. The static methods above all work on the `APP` scope. `Session::scope()` returns a `Laika\Session\Scope` with the same methods for any other scope:

| Method | Returns | Does |
|---|---|---|
| `set(string $key, mixed $value)` | `void` | Stores one value |
| `get(string $key, mixed $default = null)` | `mixed` | Reads one value, or `$default` when missing |
| `has(string $key)` | `bool` | Whether the key is set and not `null` |
| `pop(string $key)` | `void` | Removes one key if present |
| `purge()` | `void` | Removes every key in this scope |
| `all()` | `array` | Every key in this scope, or `[]` |
| `name()` | `string` | The normalized scope name |

```php
$auth = Session::scope('AUTH');

$auth->set('token', 'abc123');
$auth->get('token');   // 'abc123'
Session::get('token'); // null, because the APP scope is separate

Session::scope('USER')->set('id', 42);
Session::scope('CART')->set('id', 99);

Session::scope('USER')->get('id'); // 42
Session::scope('CART')->get('id'); // 99
```

Scope names are trimmed and uppercased, so `'auth'`, `' Auth '` and `'AUTH'` all refer to the same scope. An empty name throws `InvalidArgumentException`.

A `Scope` holds only its name. It is safe to keep one in a property, or to create a new one for each call.

## Regenerating the ID

Issue a new session ID whenever privilege changes: on login, on logout, and on role changes. This keeps an ID that leaked earlier from inheriting the new privileges:

```php
// after verifying credentials
Session::regenerate();
Session::set('user_id', $user['id']);
```

`regenerate()` with no argument deletes the old session's stored data. `regenerate(false)` keeps the old record around. Only use that when an in-flight request still needs it, since the old ID stays valid until it expires.

## Destroying the Session

```php
Session::destroy();
```

This empties `$_SESSION`, deletes the stored session and expires the cookie in the browser. If output has already been sent, the cookie can't be cleared, but the stored data is still deleted, so the old ID no longer resolves to anything.

## SessionManager

`SessionManager` runs the lifecycle behind the facade:

```php
use Laika\Session\SessionManager;

SessionManager::isConfigured(); // has a driver been selected?
SessionManager::isStarted();    // is a session active right now?
SessionManager::start();        // start explicitly; safe to call repeatedly
SessionManager::handler();      // the active driver instance
SessionManager::destroy();      // same as Session::destroy()
```

You need `start()` only when you want the session cookie sent at a specific point, for example before streaming output. [Reference](06_reference.md#lifecycle) describes what it does step by step.

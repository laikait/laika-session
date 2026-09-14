# Reference

## Lifecycle

`SessionManager::start()` runs on the first `Session::` call of a request. Later calls do nothing. It:

1. Returns immediately when a session is already active, including one started outside this package.
2. Builds the driver `SessionConfig` selected, once per process. It throws `SessionHandlerException` when none was selected.
3. Calls the driver's `setup()`, once per process. This is where `install => true` creates the table.
4. Registers the driver with `session_set_save_handler()`.
5. Applies the cookie parameters, unless `session.use_cookies` is off.
6. Calls `session_start()` with the merged options.

A failed `session_start()` isn't recorded as started, so the next call tries again rather than assuming there's a session.

Redis and Memcached receive `gc_maxlifetime` as their `lifetime` unless you pass one yourself.

## Driver Contract

Every driver implements `Laika\Session\Contracts\SessionDriverInterface`:

```php
interface SessionDriverInterface extends SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function setup(): void; // prepare the store; once per process, before the session starts
}
```

That adds up to these methods:
- `setup()`
- the standard `open()`, `close()`, `read()`, `write()`, `destroy()` and `gc()`
- `validateId()`, which reports whether a session with this ID exists. `use_strict_mode` relies on it.
- `updateTimestamp()`, which extends the expiry without rewriting the data. PHP calls it instead of `write()` when the data hasn't changed, so a driver that only extends expiry inside `write()` expires users who are still active.

`HandlerFactory` builds only the five built-in drivers. There is currently no hook for registering your own driver through `SessionConfig`.

## Exceptions

Everything is thrown as `Laika\Session\Exceptions\SessionHandlerException`:

| When | Message starts with |
|---|---|
| First `Session::` call with no driver selected | `No session driver configured.` |
| File driver `path` missing or not a directory | `Session path [...] is invalid or doesn't exists!!` |
| `mysql` without a PDO instance | `The [mysql] session driver needs a connected PDO instance.` |
| `mysql` table name outside `[A-Za-z0-9_]` | `Session table [...] is not a valid table name.` |
| `redis` without a `Redis` instance | `The [redis] session driver needs a connected Redis instance.` |
| `memcached` without a `Memcached` instance | `The [memcached] session driver needs a configured Memcached instance.` |
| `model` without laikait/laika-model installed | `The [model] session driver needs laikait/laika-model.` |

Errors that occur during a request after the session has started don't throw. Failed reads come back as an empty session, and failed writes are reported by PHP as warnings.

## Testing

All state is static, so reset it between test cases:

```php
protected function tearDown(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    \Laika\Session\SessionManager::reset(); // handler, started flag, setup flag
    \Laika\Session\SessionConfig::reset();  // driver, params, options, cookies
}
```

The package's own suite:

```bash
composer install
vendor/bin/phpunit
composer compat   # PHP 8.1–8.5 compatibility check
```

Driver tests skip themselves when their backend is unreachable. Point them at real servers with these environment variables:

| Driver | Variables |
|---|---|
| mysql | `LAIKA_SESSION_DSN`, `LAIKA_SESSION_USER`, `LAIKA_SESSION_PASS` |
| model | `LAIKA_SESSION_MYSQL_HOST`, `LAIKA_SESSION_MYSQL_PORT`, `LAIKA_SESSION_MYSQL_DB`, `LAIKA_SESSION_USER`, `LAIKA_SESSION_PASS` |
| redis | `LAIKA_SESSION_REDIS_HOST`, `LAIKA_SESSION_REDIS_PORT` |
| memcached | `LAIKA_SESSION_MEMCACHED_HOST`, `LAIKA_SESSION_MEMCACHED_PORT` |

## Upgrading From v4

See the migration table in the package [README](../README.md#upgrading-from-v4).

# Drivers

Select exactly one. Calling a second driver method switches to that driver and replaces the previous driver's parameters.

## Choosing a Driver

| Driver | Storage | Multiple servers | Locks per session | Garbage collection |
|---|---|---|---|---|
| `file` | Files on disk | No, unless the directory is shared | Yes | Deletes files by modification time |
| `mysql` | Table via raw PDO | Yes | No | Deletes rows by `last_activity` |
| `model` | Table via laika-model | Yes | No | Deletes rows by `last_activity` |
| `redis` | Redis keys with a TTL | Yes | No | None needed, keys expire |
| `memcached` | Memcached items with a TTL | Yes | No | None needed, items expire |

"Locks per session" matters when one user sends several requests at once, such as parallel AJAX calls. See [Locking and Concurrency](#locking-and-concurrency).

Every driver implements `validateId()` and `updateTimestamp()`. As a result, `use_strict_mode` rejects session IDs the server never issued, and a user who only reads the session is not logged out when their session goes unwritten for a while.

## File

```php
SessionConfig::file([
    'path'   => '/var/www/app/storage/sessions', // optional
    'prefix' => 'LK',                            // optional, default 'LK'
]);
```

| Param | Default | Notes |
|---|---|---|
| `path` | `session_save_path()`, then the system temp directory | Must already exist. The driver throws `SessionHandlerException` rather than creating it. |
| `prefix` | `LK` | Uppercased. Files are named `<PREFIX>_<session id>`. |

How it behaves:
- Files are created with mode `0600`.
- The file is locked exclusively from the moment the session is read until it is written or closed, as PHP's built-in handler does. Concurrent requests on the same session wait their turn instead of overwriting each other.
- Session IDs are checked against `[A-Za-z0-9,-]` before they touch the filesystem. Anything else is treated as a missing session.
- Garbage collection deletes `<PREFIX>_*` files older than `gc_maxlifetime`. It never touches files with another prefix.

Give the driver its own directory in production. [Deployment](05_deployment.md#file-driver-under-php-fpm) explains why the system default often isn't a good choice.

## MySQL (Raw PDO)

Talks to PDO directly, so it needs neither laika-model nor the framework. Pass in a PDO instance you have already connected; the package never handles credentials.

```php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=app;charset=utf8mb4', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

SessionConfig::mysql($pdo, [
    'table'   => 'sessions', // optional, default 'sessions'
    'install' => false,      // optional, default false
]);
```

| Param | Default | Notes |
|---|---|---|
| `table` | `sessions` | Must match `[A-Za-z0-9_]+`, because it is interpolated into the SQL. All other values are bound. |
| `install` | `false` | `true` runs `CREATE TABLE IF NOT EXISTS` once per process, before the session starts. |

Writes use a single `INSERT ... ON DUPLICATE KEY UPDATE`, so two first writes for the same ID can't collide. That statement is MySQL/MariaDB syntax, so this driver doesn't work on PostgreSQL or SQLite.

## Model (Laika Model)

Goes through `Laika\Session\Model\SessionModel` on a laika-model connection. Configure the connection itself in laika-model.

```php
SessionConfig::model([
    'connection' => 'default', // optional, laika-model's default connection
    'install'    => false,     // optional, default false
]);
```

| Param | Default | Notes |
|---|---|---|
| `connection` | laika-model's default | The connection must already be registered. The framework's `Init::model()` registers it for you. |
| `install` | `false` | `true` runs `SessionSchema` against the same connection, once per process. |

`SessionConfig::model()` throws `SessionHandlerException` when laika-model isn't installed. The mysql driver is the alternative when you don't have laika-model.

## The Session Table

The `mysql` and `model` drivers share one layout, so you can switch between them without a migration:

```sql
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) NOT NULL,
    `data`          BLOB NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_last_activity` (`last_activity`)
);
```

Leave `install` off in production. Creating the table at request time adds a query to the first request of every PHP process, and it needs DDL privileges your runtime database user shouldn't have. Create the table once, from a migration or by hand, with the SQL above. With laika-model you can also run `(new \Laika\Session\Schema\SessionSchema('default'))->up();`.

`last_activity` is written using PHP's clock (`date()`), not the database's. Keep `date.timezone` the same on every server that shares the table. Otherwise garbage collection compares timestamps from different zones.

## Redis

Pass a client that is already connected and authenticated:

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('secret'); // if needed

SessionConfig::redis($redis, [
    'prefix'   => 'LK',  // optional, default 'LK'
    'lifetime' => 1440,  // optional, seconds
]);
```

| Param | Default | Notes |
|---|---|---|
| `prefix` | `LK` | Uppercased. Keys are `<PREFIX>_<session id>`. |
| `lifetime` | `gc_maxlifetime` from [session options](03_configuration.md#session-options) | The TTL is set on every write and renewed on every read-only request. |

A `RedisException` during a request doesn't crash the page. A failed read returns an empty session, and a failed write returns `false`, which PHP reports as a warning. So an outage looks like users being logged out, not like an error page.

## Memcached

Pass a client with at least one server added:

```php
$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

SessionConfig::memcached($memcached, [
    'prefix'   => 'LK',  // optional, default 'LK'
    'lifetime' => 1440,  // optional, seconds
]);
```

Parameters are the same as for Redis. Some things to know:
- `addServer()` doesn't connect, so an unreachable server shows up as an empty session, not as an exception.
- Where the server build supports `touch`, the TTL is renewed with it. Otherwise the driver falls back to a full write.
- Memcached evicts items when it runs short of memory, which can end sessions early. Use Redis when losing a session is costly.

## Locking and Concurrency

Only the file driver locks. With the other four, two requests on the same session that overlap each read the session, change it, and write it back. The second write wins, and changes made only by the first request are lost.

In practice:
- Don't rely on counters or other read-modify-write state in the session when a page fires parallel requests.
- Under the file driver, a slow request blocks every other request from the same user until it finishes. When a long request is done with the session, release it early with PHP's `session_write_close()`.

See [Deployment](05_deployment.md) for how this plays out under PHP-FPM.

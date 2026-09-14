# Deployment

## One Server or Many

The file driver keeps sessions on the local disk. Behind a load balancer with several application servers, a user whose requests land on different servers loses their session. For more than one server, use one of these:
- `redis`, `mysql` or `model`, which are shared stores
- `memcached`, accepting that it may evict sessions under memory pressure
- sticky sessions on the load balancer, as a last resort

## File Driver Under PHP-FPM

Always pass the file driver an explicit `path` in production:

```php
SessionConfig::file(['path' => '/var/www/app/storage/sessions']);
```

The default, `session_save_path()`, causes trouble on common FPM setups:
- **Debian and Ubuntu:** the default is `/var/lib/php/sessions`, which is not listable (mode `1733`), and the distribution cleans it with a cron job that only deletes `sess_*` files. This driver's files are named `LK_*`, so the cron job never removes them, and the driver's own garbage collection can't list the directory. Expired sessions pile up.
- **systemd `PrivateTmp`:** when the save path falls back to the temp directory, php-fpm's private `/tmp` is wiped on every restart, logging everyone out. The CLI also sees a different `/tmp` from the web server.

For the directory you choose:
- Create it during deployment. The driver doesn't create it.
- Make it writable by the FPM pool user, often `www-data`.
- Keep it outside the web root.

Files are created with mode `0600`. If a CLI process running as another user creates session files, the pool user can't open them, and those users get empty sessions.

## HTTPS and Proxies

If TLS terminates in front of PHP, the cookie `secure` flag can't detect HTTPS on its own. See [Behind a Proxy or Load Balancer](03_configuration.md#behind-a-proxy-or-load-balancer).

## Concurrent Requests

Under PHP-FPM, one user's parallel requests really do run at the same time in separate worker processes:
- **File driver:** requests on the same session queue on the file lock. One slow request, such as a report or an upload, holds up every other request from that user. Call `session_write_close()` as soon as a long request has finished writing to the session.
- **Other drivers:** nothing queues, and the last write wins. See [Locking and Concurrency](02_drivers.md#locking-and-concurrency).

## Garbage Collection

With the defaults (`gc_probability = 1`, `gc_divisor = 100`), roughly one session start in a hundred runs a cleanup:

| Driver | Cleanup |
|---|---|
| `file` | Scans the directory for expired `<PREFIX>_*` files |
| `mysql` / `model` | One `DELETE ... WHERE last_activity < ?`, which uses the `last_activity` index |
| `redis` / `memcached` | Nothing to do, the server expires keys |

On a busy site with the file driver, the directory scan runs inside a user's request. To take it off the request path:
1. Set `gc_probability` to `0`.
2. Run a scheduled job that deletes expired files. Match on this driver's prefix, not on `sess_*`.

## Database Drivers in Production

- Create the `sessions` table ahead of time. Keep `install` set to `false` so the runtime user needs no DDL privileges. See [The Session Table](02_drivers.md#the-session-table).
- Keep `date.timezone` identical on every server, and in the CLI, when they share the table.
- The session table grows with traffic. It's indexed on `last_activity` for cleanup, and on `id` for every read.

## Pre-Deploy Checklist

- [ ] The driver is selected before the first `Session::` call, and before any output.
- [ ] File driver: an explicit `path` that exists, is writable by the pool user, and is outside the web root.
- [ ] Database drivers: the table exists and `install` is `false`.
- [ ] `secure` resolves to `true` on HTTPS, including behind your proxy.
- [ ] The `samesite` setting fits how people reach the site.
- [ ] `gc_maxlifetime` matches how long an idle user should stay logged in.

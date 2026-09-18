# Laika Session Documentation

`laikait/laika-session` replaces PHP's session storage with a pluggable driver: files, Redis, Memcached, MySQL over raw PDO, or a Laika Model table. It works on its own or inside the Laika Framework.

| Page | What it covers |
|---|---|
| [Getting Started](01_getting-started.md) | Requirements, installation, first session, use inside the framework |
| [Drivers](02_drivers.md) | The five drivers, their parameters, the database table, locking behaviour |
| [Configuration](03_configuration.md) | Session options, cookie parameters, the `secure` flag |
| [Session API](04_session-api.md) | The `Session` class, namespaces, `SessionManager` |
| [Deployment](05_deployment.md) | PHP-FPM, multiple servers, proxies, concurrency, garbage collection |
| [Reference](06_reference.md) | Lifecycle, driver contract, exceptions, testing |

New to the package? Start with [Getting Started](01_getting-started.md).

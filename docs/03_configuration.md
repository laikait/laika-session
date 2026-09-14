# Configuration

`SessionConfig::options()` and `SessionConfig::cookies()` are plain setters:
- Each call merges over the defaults, and over earlier calls, so a partial call leaves everything else intact.
- Each returns the full merged set, so calling it with no arguments reads the current values.
- Neither needs a driver to be selected first.

Both take effect when the session starts, which is the first `Session::` call. Changes made after the session has started only apply from the next request.

## Session Options

These are passed to `session_start()`:

```php
SessionConfig::options([
    'name'           => 'MY_APP', // cookie name
    'gc_maxlifetime' => 3600,     // seconds
]);
```

| Option | Default | Meaning |
|---|---|---|
| `name` | `LFSESS` | Session cookie name |
| `use_only_cookies` | `true` | Never accept a session ID from the URL |
| `use_strict_mode` | `true` | Reject session IDs the server never issued, which prevents session fixation |
| `gc_probability` | `1` | Together with `gc_divisor`: run garbage collection on 1 in 100 session starts |
| `gc_divisor` | `100` | See above |
| `gc_maxlifetime` | `1440` | Seconds of inactivity before a session counts as expired. Also the Redis and Memcached TTL. |

Any other `session.*` directive, written without the `session.` prefix, is also accepted, for example `'lazy_write' => true`. Avoid directives PHP has deprecated, such as `sid_length` in PHP 8.4. They raise deprecation notices, and the Laika Framework turns those into exceptions.

`gc_maxlifetime` measures inactivity, not total session age. Every request renews the session, including requests that don't change it.

## Cookie Parameters

These are passed to `session_set_cookie_params()`:

```php
SessionConfig::cookies([
    'domain'   => '.example.com',
    'samesite' => 'Lax',
]);
```

| Param | Default | Meaning |
|---|---|---|
| `path` | `/` | Path the cookie is sent for |
| `domain` | *(unset)* | Leave it unset for a cookie limited to the current host. Setting it widens the cookie to subdomains. |
| `secure` | *follows the connection* | Send only over HTTPS. See below. |
| `httponly` | `true` | Hide the cookie from JavaScript |
| `samesite` | `Strict` | `Strict`, `Lax` or `None` |
| `lifetime` | `0` | Cookie lifetime in seconds. `0` means until the browser closes. |

If `session.use_cookies` is disabled in `php.ini`, cookie parameters are skipped entirely.

### Choosing SameSite

`Strict` withholds the cookie on every cross-site request, including a plain link click from another site. A user who follows a link from an email or search result arrives looking logged out until they navigate again. `Lax` still blocks cross-site form posts, but sends the cookie on top-level link navigation. It is usually the better choice for sites people link to.

`None` requires `secure => true`, or browsers drop the cookie.

## The Secure Flag

By default, `secure` is decided per request:
- `true` when `$_SERVER['HTTPS']` is set (and not `off`), or when the server port is 443
- `false` otherwise

It isn't hardcoded to `true` because a `Secure` cookie on a plain-HTTP development machine never comes back from the browser. Every request would then silently start a new session, with no error to explain it.

### Behind a Proxy or Load Balancer

When TLS terminates at a proxy, CDN or load balancer, PHP sees a plain-HTTP request on port 80. The automatic check then returns `false`, and the cookie goes out without `Secure`. This package can't trust `X-Forwarded-Proto` on its own, because any client can send that header. Choose one of these:

- **Inside the Laika Framework:** list your proxies in `trusted_proxies` in `lf-config/app.php`. `Init`'s driver methods then mark the cookie `Secure` whenever the framework detects HTTPS through a trusted proxy.
- **Standalone:** set it yourself once you know the site is HTTPS-only:

  ```php
  SessionConfig::cookies(['secure' => true]);
  ```

- **Web server:** pass `HTTPS on` to PHP from the web server, for example `fastcgi_param HTTPS on;` in nginx.

## Resetting State

`SessionConfig::reset()` clears the driver, parameters, options and cookie parameters. It exists for tests; see [Reference](06_reference.md#testing).

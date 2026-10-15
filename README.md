<p align="center"><img src="https://zanbug.com/logo.png" alt="Zanbug" width="120"></p>

# zanbug/symfony

Symfony bundle for the [Zanbug](https://zanbug.com) error & monitoring SDK.
It auto-captures unhandled exceptions via a `kernel.exception` listener and
initializes the framework-agnostic `zanbug/php-sdk` core for you.

Compatible with Symfony **3.4 → 7.x** and PHP **7.1+**.

## Install

```bash
composer require zanbug/symfony
```

## Enable the bundle

Add it to `config/bundles.php` (Symfony Flex / 4+):

```php
// config/bundles.php
return [
    // ...
    Zanbug\Symfony\ZanbugBundle::class => ['all' => true],
];
```

On Symfony 3.4 (`AppKernel`), add it to `registerBundles()`:

```php
public function registerBundles()
{
    $bundles = [
        // ...
        new Zanbug\Symfony\ZanbugBundle(),
    ];
    return $bundles;
}
```

## Configure

Create `config/packages/zanbug.yaml`:

```yaml
zanbug:
    api_key: '%env(ZANBUG_API_KEY)%'
    host: 'https://zanbug.com'
    # environment: '%kernel.environment%'
    # release: '1.0.0'
    # enabled: true
    # capture_requests: false
    # capture_queries: true
    # slow_query_ms: 1000
    # sample_rate: 1.0
```

Then set your API key (e.g. in `.env`):

```dotenv
ZANBUG_API_KEY=bb_xxxxxxxxxxxxxxxx
```

That's it — unhandled exceptions are now reported to Zanbug automatically.

## Manual capture

You can also report manually anywhere in your app:

```php
use Zanbug\Sdk\Zanbug;

try {
    // ...
} catch (\Throwable $e) {
    Zanbug::capture($e);
}

// Or a message:
Zanbug::captureMessage('Something noteworthy happened', 'warning');
```

## Configuration reference

| Key                | Type    | Default                 |
|--------------------|---------|-------------------------|
| `api_key`          | string  | `''`                    |
| `host`             | string  | `https://zanbug.com` |
| `environment`      | string  | `production`            |
| `release`          | string  | `null`                  |
| `enabled`          | bool    | `true`                  |
| `capture_requests` | bool    | `false`                 |
| `capture_queries`  | bool    | `true`                  |
| `slow_query_ms`    | int     | `1000`                  |
| `sample_rate`      | float   | `1.0`                   |

## Slow query monitoring

Doctrine's `SQLLogger` interface was removed in DBAL 4, so the bundle does **not** hook Doctrine automatically. Pick whichever fits your setup (queries faster than `slow_query_ms` are always dropped by the SDK):

- **Doctrine DBAL 2.x / 3.x** — register the bundled logger yourself (e.g. on `kernel.request` or in a decorator):

  ```php
  if (interface_exists(\Doctrine\DBAL\Logging\SQLLogger::class)) {
      $connection->getConfiguration()->setSQLLogger(new \Zanbug\Symfony\Doctrine\ZanbugSqlLogger());
  }
  ```

- **Any DBAL version / no Doctrine** — record manually where you run queries:

  ```php
  \Zanbug\Sdk\Zanbug::recordQuery($sql, $durationMs, ['connection' => 'mysql', 'bindings' => $params]);
  ```

- **Plain PDO** — use the drop-in `\Zanbug\Sdk\Support\TracedPdo` from the core SDK.

## Updating the SDK
```bash
vendor/bin/zanbug check      # exit 10 when a newer version is published
vendor/bin/zanbug update     # composer require zanbug/php-sdk:^<latest> zanbug/symfony:^<latest>
```
Automatic: `ZANBUG_AUTO_UPDATE=true` (or `'auto_update' => true` in the SDK config) — once a day at the end of a CLI run the SDK updates itself in a detached process (log `<tmp>/zanbug-update.log`). Restart workers afterwards. Details: [zanbug/php-sdk README](https://github.com/zanbug/php-sdk#updating-the-sdk).

## Log capture (errors logged but not thrown)

Errors you catch and log — but never re-throw — only hit the log file. Enable
`capture_logs` in the SDK init and forward them to Zanbug with `recordLog()`:

```php
\Zanbug\Sdk\Zanbug::init([
    'api_key'      => 'bb_xxxxxxxx',
    'host'         => 'https://zanbug.com',
    'capture_logs' => true,
    'log_level'    => 'error', // minimum PSR level forwarded
]);

// Any framework — from your log pipeline or directly:
\Zanbug\Sdk\Zanbug::recordLog('error', 'Import failed', ['file' => $name]);

// Caught-and-logged throwable (attach it for a full stacktrace):
try { risky(); } catch (\Throwable $e) {
    \Zanbug\Sdk\Zanbug::recordLog('error', $e->getMessage(), ['exception' => $e]);
}
```

**Monolog** users can register a tiny handler whose `write()` calls
`Zanbug::recordLog($record['level_name'] ?? $record->level->getName(), $record['message'] ?? $record->message, $record['context'] ?? $record->context)`
(works for Monolog 2 arrays and Monolog 3 `LogRecord`). Records below `log_level`
are dropped; context is redacted; `recordLog()` never throws.

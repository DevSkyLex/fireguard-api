<?php

declare(strict_types=1);

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

/**
 * Point this process at its own copy of a test database.
 *
 * Every run gets `<dbname>_w<token>`, cloned from the migrated and seeded
 * database `make test-db` built: `CREATE DATABASE ... TEMPLATE` is a file-level
 * copy, so this costs a fraction of a second rather than replaying the
 * migrations.
 *
 * This is what makes a run hermetic, and it is not only a paratest concern.
 * DAMA wraps each *test* in a transaction it rolls back, which isolates tests
 * from each other inside one process — but it does nothing about the process
 * next door. Sharing `fireguard_*_test` between runs means a concurrent run, a
 * `make test-db` reseed, a psql session or the wreckage of a run killed
 * mid-suite all land in the same tables the suite is asserting exact counts
 * over, and the E2E suite drifts by a few rows in whichever direction that
 * process happened to write. A clone per run removes the shared surface
 * entirely: nothing else can reach these tables for the lifetime of the run.
 *
 * The clone is rebuilt every run rather than reused, so a run can never inherit
 * a schema left behind by an older migration state, nor rows left behind by an
 * older run, and it is dropped again on shutdown so clones do not accumulate.
 *
 * @param string $dsn the configured PostgreSQL DSN, pointing at the template
 * @param string $token the token namespacing this process
 *
 * @return string the same DSN with its database name swapped for the clone's
 */
function fireguard_isolated_database_url(string $dsn, string $token): string
{
  $parts = parse_url($dsn);

  if (!is_array($parts) || !isset($parts['host'], $parts['path'])) {
    throw new RuntimeException('Unable to parse the test database DSN for an isolated test run.');
  }

  $template = ltrim((string) $parts['path'], '/');
  $suffix = preg_replace('/[^A-Za-z0-9_]/', '', $token) ?? '';
  $clone = $template . '_w' . $suffix;

  $quote = static fn (string $identifier): string => '"' . str_replace('"', '""', $identifier) . '"';

  $host = (string) $parts['host'];
  $port = (int) ($parts['port'] ?? 5432);
  $maintenance = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=postgres', $host, $port),
    isset($parts['user']) ? urldecode((string) $parts['user']) : null,
    isset($parts['pass']) ? urldecode((string) $parts['pass']) : null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
  );

  // CREATE ... TEMPLATE refuses to run while any session is attached to the
  // template, so a session that will never let go has to be cleared — but that
  // is a last resort, not an opening move.
  //
  // Evicting unconditionally and up front is what produced the
  // "terminating connection due to administrator command" deaths: whatever
  // held the template was killed on sight the moment another run started
  // cloning, and the victim was usually a healthy `phpunit` run doing its work
  // against the shared database. Now that every run clones, nothing holds the
  // template in the normal case and the very first attempt succeeds, so this
  // retry loop is only reached when something genuinely holds it.
  //
  // That something is either a stuck backend from a TEST_DB_ISOLATION=0 run
  // that died mid-transaction — which nothing but eviction clears, short of
  // restarting PostgreSQL — or a `make test-db` reseed in flight. The two are
  // indistinguishable from here, and the ~2s of retries below is nowhere near
  // long enough to wait a reseed out, so a concurrent reseed does get killed.
  // That is survivable (its migrations and its fixture load are each wrapped
  // in a transaction, so it rolls back rather than leaving a half-built
  // template) and it fails loudly, where a stuck backend would otherwise wedge
  // every run on the machine silently. Don't run the suite against a database
  // that is being reseeded.
  $evict = $maintenance->prepare(
    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity'
      . ' WHERE datname = :template AND pid <> pg_backend_pid()'
      . " AND state IN ('idle', 'idle in transaction', 'idle in transaction (aborted)')",
  );

  $attempts = 0;
  while (true) {
    try {
      if ($attempts >= 4) {
        $evict->execute(['template' => $template]);
      }

      $maintenance->exec(sprintf('DROP DATABASE IF EXISTS %s WITH (FORCE)', $quote($clone)));
      $maintenance->exec(sprintf('CREATE DATABASE %s TEMPLATE %s', $quote($clone), $quote($template)));

      break;
    } catch (PDOException $exception) {
      if (++$attempts >= 10) {
        throw new RuntimeException(
          sprintf(
            'Could not clone "%s" into "%s" for an isolated test run (%s). Run `make test-db` first.',
            $template,
            $clone,
            $exception->getMessage(),
          ),
          0,
          $exception,
        );
      }

      usleep(500_000);
    }
  }

  // Drop the clone again when this process ends, so an ordinary `phpunit` run
  // leaves no trace. A run killed outright (Ctrl-C, SIGKILL) skips this and
  // leaks its clone; `make test-db-clean` is the sweep for that case.
  register_shutdown_function(static function () use ($host, $port, $parts, $clone, $quote): void {
    try {
      new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=postgres', $host, $port),
        isset($parts['user']) ? urldecode((string) $parts['user']) : null,
        isset($parts['pass']) ? urldecode((string) $parts['pass']) : null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
      )->exec(sprintf('DROP DATABASE IF EXISTS %s WITH (FORCE)', $quote($clone)));
    } catch (PDOException) {
      // Best effort: a leaked clone costs disk, a throw here would mask the
      // run's own exit status.
    }
  });

  $parts['path'] = '/' . $clone;

  return sprintf(
    '%s://%s%s%s%s',
    $parts['scheme'] ?? 'postgresql',
    isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '',
    $host,
    isset($parts['port']) ? ':' . $parts['port'] : '',
    $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : ''),
  );
}

/**
 * Fingerprint everything the compiled Symfony container is built from.
 *
 * The test kernel boots with debug off — phpunit.dist.xml and phpunit.e2e.xml
 * both force APP_DEBUG=0 — and a non-debug kernel never revalidates its
 * container cache. Symfony still writes the resource list to `*.meta`, but only
 * consults it in debug mode, so whatever sits in the cache directory is what the
 * run executes, however old it is.
 *
 * That is harmless only while the directory belongs to one run. It did not:
 * paratest exports TEST_TOKEN=1..N and reuses those same tokens on every run, so
 * each worker picked up the container it had compiled days earlier. Add a
 * constructor argument to an autowired service and the generated factory still
 * passes the old ones — `ArgumentCountError: 2 passed ... exactly 3 expected`,
 * thrown from inside the cached factory and surfacing, with debug off, as a bare
 * HTTP 500. Plain `phpunit` looked green throughout only because it draws a
 * random token, which is to say it never reused a cache directory at all — and
 * paid ~14s recompiling the container on every single run, leaving the directory
 * behind afterwards.
 *
 * Keying the directory on the inputs settles both halves: identical sources find
 * their container already built, and any change lands on a path nothing has
 * compiled yet.
 *
 * Paths, sizes and mtimes rather than file contents. This runs once per worker
 * process — paratest's WrapperRunner keeps workers alive across test files, so
 * it is `-p` times per run, not once per test — and stat-ing ~3k files costs
 * ~0.25s where digesting their contents costs far more. The trade is mtime
 * granularity: an edit that preserves both size and mtime is invisible, which in
 * practice takes a deliberate `touch -r`.
 *
 * @param string $projectDir the project root
 * @param string $environment the Symfony environment being booted
 *
 * @return string a short hex digest of the container's inputs
 */
function fireguard_container_fingerprint(string $projectDir, string $environment): string
{
  $digest = hash_init('xxh128');
  hash_update($digest, $environment);

  // src/ and config/ hold the service definitions and the autowiring metadata;
  // templates/ and translations/ are compiled into the same directory and go
  // stale in exactly the same way.
  foreach (['config', 'src', 'templates', 'translations'] as $directory) {
    $root = $projectDir . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($root)) {
      continue;
    }

    $entries = [];
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $item) {
      if ($item instanceof SplFileInfo && $item->isFile()) {
        $entries[] = $item->getPathname() . '|' . $item->getSize() . '|' . $item->getMTime();
      }
    }

    // Directory iteration order is filesystem-defined; sorting keeps the digest
    // from depending on it.
    sort($entries);
    hash_update($digest, implode("\n", $entries));
  }

  // composer.lock stands in for the installed bundle set, and the dotenv files
  // for any parameter resolved at compile time rather than through %env()%.
  foreach (['composer.lock', '.env', '.env.dist', '.env.local', '.env.test', '.env.test.local'] as $file) {
    $path = $projectDir . DIRECTORY_SEPARATOR . $file;

    hash_update(
      $digest,
      is_file($path) ? $file . '|' . filesize($path) . '|' . filemtime($path) : $file . '|absent',
    );
  }

  return substr(hash_final($digest), 0, 16);
}

/**
 * Drop container caches nothing has booted for a week.
 *
 * One directory per distinct source state means one more directory every time a
 * tracked file changes, so they do accumulate — just bounded by how often the
 * tree changes rather than by how often the suite runs. Pruning is deliberately
 * only attempted when a fingerprint turns out to be new, because that is already
 * the path that pays ~14s to compile a container: a few hundred milliseconds of
 * unlinking there is invisible, and on the reuse path it would not be.
 *
 * Best effort throughout. A directory another worker is compiling into right now
 * is younger than the cutoff and never a candidate, and anything that does fail
 * to unlink costs disk, not correctness.
 *
 * @param string $root the directory holding the per-fingerprint caches
 * @param string $keep the fingerprint this run needs
 * @param int $maxAgeSeconds how long an unused fingerprint survives
 */
function fireguard_prune_stale_test_caches(string $root, string $keep, int $maxAgeSeconds = 604800): void
{
  $entries = @scandir($root);

  if (false === $entries) {
    return;
  }

  $cutoff = time() - $maxAgeSeconds;

  foreach ($entries as $entry) {
    if ('.' === $entry || '..' === $entry || $entry === $keep) {
      continue;
    }

    $path = $root . DIRECTORY_SEPARATOR . $entry;
    $modified = @filemtime($path);

    if (!is_dir($path) || false === $modified || $modified >= $cutoff) {
      continue;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
      if ($item instanceof SplFileInfo) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
      }
    }

    @rmdir($path);
  }
}

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
  $testToken = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;

  // Both paratest and Infection export TEST_TOKEN; a plain `phpunit` run does
  // not. The token namespaces this process's cache and log directories, so an
  // externally supplied one must be kept verbatim — Infection reuses a thread's
  // token across every mutant it runs there, and overwriting it would recompile
  // the Symfony container once per mutant.
  $providedToken = is_string($testToken) || is_int($testToken) ? (string) $testToken : '';
  $hasExternalToken = '' !== $providedToken;
  $testToken = $hasExternalToken ? $providedToken : bin2hex(random_bytes(6));
  $_SERVER['TEST_TOKEN'] = $_ENV['TEST_TOKEN'] = $testToken;

  // Every run gets its own database copy, not just paratest workers: a plain
  // `phpunit` run sharing `fireguard_*_test` with whatever else is running is
  // exactly how the E2E suite's exact-count assertions drift.
  //
  // Infection is the one exception. It spawns a process per mutant — thousands
  // of them — so cloning there would rebuild two databases thousands of times,
  // and it needs no isolation anyway: infection.json5 pins mutation testing to
  // `--testsuite=General Unit Tests`, which opens no connection. Should that
  // ever cover a DB-backed suite, this guard is what has to change.
  //
  // TEST_DB_ISOLATION=0 opts out, for when you need to inspect the database a
  // failing test left behind — the clone is dropped on shutdown, so a failure
  // under isolation cannot be autopsied after the fact.
  $underInfection = null !== ($_SERVER['INFECTION'] ?? $_ENV['INFECTION'] ?? null);
  $isolateDatabases = filter_var(
    $_SERVER['TEST_DB_ISOLATION'] ?? $_ENV['TEST_DB_ISOLATION'] ?? true,
    FILTER_VALIDATE_BOOLEAN,
  );
  $usesOwnDatabases = $isolateDatabases && !$underInfection;
  $environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'test';
  if (!is_string($environment) || '' === $environment) {
    $environment = 'test';
  }

  // Cache and log directories hang off the fingerprint, not the token. The token
  // still separates concurrent workers underneath it — two processes compiling
  // into one directory is a race Symfony's own lock covers but Doctrine's proxy
  // writer need not — while the fingerprint is what decides whether a container
  // may be reused at all.
  //
  // A plain `phpunit` run parks on a fixed "solo" slot rather than the random
  // token it draws for its databases: that token exists to keep two runs off
  // each other's rows, and a cache directory is the one thing they should share.
  // Sharing it is what turns the ~14s container compile into a once-per-change
  // cost instead of a once-per-run one, and stops the run leaking a directory
  // behind it.
  $fingerprint = fireguard_container_fingerprint(dirname(__DIR__), $environment);
  $cacheRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fireguard-auth';
  $fingerprintDir = $cacheRoot . DIRECTORY_SEPARATOR . $fingerprint;
  // Scrubbed the same way the database clone scrubs it: the token arrives from
  // the environment and is about to become a path segment.
  $cacheSlot = $hasExternalToken ? (preg_replace('/[^A-Za-z0-9_]/', '', $providedToken) ?? '') : 'solo';
  $baseTempDir = $fingerprintDir . DIRECTORY_SEPARATOR . ('' !== $cacheSlot ? $cacheSlot : 'solo');
  $cacheDir = $baseTempDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $environment;
  $logDir = $baseTempDir . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . $environment;

  $_SERVER['APP_CACHE_DIR'] = $_ENV['APP_CACHE_DIR'] = $cacheDir;
  $_SERVER['APP_LOG_DIR'] = $_ENV['APP_LOG_DIR'] = $logDir;

  $freshFingerprint = !is_dir($fingerprintDir);

  if (!is_dir($baseTempDir)) {
    mkdir($baseTempDir, 0777, true);
  }
  if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
  }
  if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
  }

  // Stamp the fingerprint directory on every boot so the sweep below measures
  // "unused for a week" rather than "created a week ago" — a fingerprint that
  // survives months of daily runs must not be collected out from under them.
  @touch($fingerprintDir);

  if ($freshFingerprint) {
    fireguard_prune_stale_test_caches($cacheRoot, $fingerprint);
  }

  $clearCache = filter_var($_SERVER['CLEAR_TEST_CACHE'] ?? $_ENV['CLEAR_TEST_CACHE'] ?? false, FILTER_VALIDATE_BOOLEAN);
  if ($clearCache && is_dir($cacheDir)) {
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
      if (!$item instanceof SplFileInfo) {
        continue;
      }
      $path = $item->getPathname();
      if ($item->isDir()) {
        @rmdir($path);
      } else {
        @unlink($path);
      }
    }

    @rmdir($cacheDir);
    mkdir($cacheDir, 0777, true);
  }

  $resetSchema = filter_var($_SERVER['TEST_DB_RESET'] ?? $_ENV['TEST_DB_RESET'] ?? false, FILTER_VALIDATE_BOOLEAN);

  // The suite runs on PostgreSQL because production does, and the repositories
  // now emit PostgreSQL-only SQL with no portable fallback. Refuse to start on
  // anything else rather than degrade: an unset URL used to relocate the
  // connection to a throwaway SQLite file, so a broken `.env.test` produced a
  // green suite that had exercised none of the shipping queries.
  foreach (['AUTH_DATABASE_URL', 'MAIN_DATABASE_URL'] as $envVar) {
    $databaseUrl = $_SERVER[$envVar] ?? $_ENV[$envVar] ?? '';

    if (!is_string($databaseUrl) || '' === $databaseUrl) {
      throw new RuntimeException(sprintf(
        '%s is empty or unset. The test suite requires PostgreSQL: run `make docker-up && make test-db`.',
        $envVar,
      ));
    }

    $scheme = strstr($databaseUrl, ':', true);

    if (!in_array($scheme, ['postgresql', 'postgres'], true)) {
      throw new RuntimeException(sprintf(
        '%s must be a PostgreSQL DSN, got scheme "%s". The suite has no portable fallback.',
        $envVar,
        false === $scheme ? '(none)' : $scheme,
      ));
    }

    if ($usesOwnDatabases) {
      $_SERVER[$envVar] = $_ENV[$envVar] = fireguard_isolated_database_url($databaseUrl, (string) $testToken);
    }
  }

  $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
  if (is_string($kernelClass) && class_exists($kernelClass) && is_subclass_of($kernelClass, KernelInterface::class)) {
    /** @var KernelInterface $kernel */
    // Boot with debug=false for schema setup only - tests will recompile with their own settings
    $kernel = new $kernelClass($environment, false);
    $kernel->boot();

    /** @var ContainerInterface $container */
    $container = $kernel->getContainer();

    /** @var list<string> $entityManagers */
    $entityManagers = [
      'doctrine.orm.auth_entity_manager',
      'doctrine.orm.main_entity_manager',
    ];

    // Opt-in only. The schema normally comes from the real migrations via
    // `make test-db`, so SchemaTool never gets to disagree with production.
    foreach ($resetSchema ? $entityManagers : [] as $serviceId) {
      /** @var Doctrine\ORM\EntityManagerInterface $entityManager */
      $entityManager = $container->get($serviceId);

      $schemaTool = new SchemaTool($entityManager);
      $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

      try {
        $schemaTool->dropSchema($metadata);
      } catch (Throwable) {
        // Schema might not exist yet
      }

      $schemaTool->createSchema($metadata);

      $entityManager->clear();
      $entityManager->getConnection()->close();
    }

    $kernel->shutdown();
  }
}

if ($_SERVER['APP_DEBUG']) {
  umask(0000);
}

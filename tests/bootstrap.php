<?php

declare(strict_types=1);

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

/**
 * Point a worker at its own copy of a test database.
 *
 * paratest runs N worker processes against the same DSN, which would have them
 * truncating and seeding each other's rows. Each worker instead gets
 * `<dbname>_<token>`, cloned from the migrated database `make test-db` built:
 * `CREATE DATABASE ... TEMPLATE` is a file-level copy, so this costs far less
 * than replaying the migrations N times.
 *
 * The clone is rebuilt every run rather than reused, so a worker can never
 * inherit a schema left behind by an older migration state.
 *
 * @param string $dsn the configured PostgreSQL DSN, pointing at the template
 * @param string $token the worker token paratest exported
 *
 * @return string the same DSN with its database name swapped for the clone's
 */
function fireguard_worker_database_url(string $dsn, string $token): string
{
  $parts = parse_url($dsn);

  if (!is_array($parts) || !isset($parts['host'], $parts['path'])) {
    throw new RuntimeException('Unable to parse the test database DSN for parallel execution.');
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
  // template. Nothing should legitimately hold it during a parallel run — the
  // workers only ever touch their own clones — but a run killed mid-test
  // leaves an "idle in transaction" backend behind that would block every
  // worker until someone restarts PostgreSQL. Clear those first, then retry
  // for the residual contention between workers cloning at the same instant.
  $evict = $maintenance->prepare(
    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :template AND pid <> pg_backend_pid()',
  );

  $attempts = 0;
  while (true) {
    try {
      $evict->execute(['template' => $template]);
      $maintenance->exec(sprintf('DROP DATABASE IF EXISTS %s WITH (FORCE)', $quote($clone)));
      $maintenance->exec(sprintf('CREATE DATABASE %s TEMPLATE %s', $quote($clone), $quote($template)));

      break;
    } catch (PDOException $exception) {
      if (++$attempts >= 10) {
        throw new RuntimeException(
          sprintf(
            'Could not clone "%s" into "%s" for parallel execution (%s). Run `make test-db` first.',
            $template,
            $clone,
            $exception->getMessage(),
          ),
          0,
          $exception,
        );
      }

      usleep(200_000);
    }
  }

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

  // Cloning the databases, on the other hand, is a paratest-only concern.
  // Infection spawns a process per mutant — thousands of them — so cloning
  // there would rebuild two databases thousands of times, and it needs no
  // isolation anyway: infection.json5 pins mutation testing to
  // `--testsuite=General Unit Tests`, which opens no connection. Should that
  // ever cover a DB-backed suite, this guard is what has to change.
  $underInfection = null !== ($_SERVER['INFECTION'] ?? $_ENV['INFECTION'] ?? null);
  $isParallelWorker = $hasExternalToken && !$underInfection;
  $tokenSuffix = '-' . $testToken;
  $baseTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fireguard-auth' . $tokenSuffix;
  $environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'test';
  if (!is_string($environment) || '' === $environment) {
    $environment = 'test';
  }
  $cacheDir = $baseTempDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $environment;
  $logDir = $baseTempDir . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . $environment;

  $_SERVER['APP_CACHE_DIR'] = $_ENV['APP_CACHE_DIR'] = $cacheDir;
  $_SERVER['APP_LOG_DIR'] = $_ENV['APP_LOG_DIR'] = $logDir;

  if (!is_dir($baseTempDir)) {
    mkdir($baseTempDir, 0777, true);
  }
  if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
  }
  if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
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

    if ($isParallelWorker) {
      $_SERVER[$envVar] = $_ENV[$envVar] = fireguard_worker_database_url($databaseUrl, (string) $testToken);
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

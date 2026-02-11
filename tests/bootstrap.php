<?php

declare(strict_types=1);

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
  $testToken = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;
  if (!is_string($testToken) || '' === $testToken) {
    $testToken = bin2hex(random_bytes(6));
    $_SERVER['TEST_TOKEN'] = $_ENV['TEST_TOKEN'] = $testToken;
  }
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

  $resetNonSqlite = filter_var($_SERVER['TEST_DB_RESET'] ?? $_ENV['TEST_DB_RESET'] ?? false, FILTER_VALIDATE_BOOLEAN);

  /** @var array<string, string> $databaseFiles */
  $databaseFiles = [
    'AUTH_DATABASE_URL' => 'test-auth.db',
    'MAIN_DATABASE_URL' => 'test-main.db',
  ];

  /** @var array<string, bool> $sqliteConnections */
  $sqliteConnections = [];

  foreach ($databaseFiles as $envVar => $sqliteFileName) {
    $databaseUrl = $_SERVER[$envVar] ?? $_ENV[$envVar] ?? '';
    if (!is_string($databaseUrl)) {
      $databaseUrl = '';
    }

    $isSqlite = '' === $databaseUrl || str_starts_with($databaseUrl, 'sqlite://');
    $sqliteConnections[$envVar] = $isSqlite;

    if (!$isSqlite) {
      continue;
    }

    $dbPath = $baseTempDir . DIRECTORY_SEPARATOR . $sqliteFileName;
    $normalizedPath = ltrim(str_replace('\\', '/', $dbPath), '/');
    $sqliteUrl = 'sqlite:///' . $normalizedPath;
    $_SERVER[$envVar] = $_ENV[$envVar] = $sqliteUrl;

    if (file_exists($dbPath)) {
      @unlink($dbPath);
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

    /** @var array<string, string> $entityManagers */
    $entityManagers = [
      'doctrine.orm.auth_entity_manager' => 'AUTH_DATABASE_URL',
      'doctrine.orm.main_entity_manager' => 'MAIN_DATABASE_URL',
    ];

    foreach ($entityManagers as $serviceId => $envVar) {
      $shouldResetSchema = $resetNonSqlite || ($sqliteConnections[$envVar] ?? false);
      if (!$shouldResetSchema) {
        continue;
      }

      /** @var Doctrine\ORM\EntityManagerInterface $entityManager */
      $entityManager = $container->get($serviceId);

      $schemaTool = new SchemaTool($entityManager);
      $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

      if ($resetNonSqlite) {
        try {
          $schemaTool->dropSchema($metadata);
        } catch (Throwable) {
          // Schema might not exist yet
        }
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

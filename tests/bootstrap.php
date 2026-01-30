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

  $databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
  if (!is_string($databaseUrl)) {
    $databaseUrl = '';
  }
  $resetNonSqlite = filter_var($_SERVER['TEST_DB_RESET'] ?? $_ENV['TEST_DB_RESET'] ?? false, FILTER_VALIDATE_BOOLEAN);
  $isSqlite = '' === $databaseUrl || str_starts_with($databaseUrl, 'sqlite://');
  if ($isSqlite) {
    $dbPath = $baseTempDir . DIRECTORY_SEPARATOR . 'test.db';
    $normalizedPath = ltrim(str_replace('\\', '/', $dbPath), '/');
    $sqliteUrl = 'sqlite:///' . $normalizedPath;
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $sqliteUrl;
    $databaseUrl = $sqliteUrl;
    if (file_exists($dbPath)) {
      @unlink($dbPath);
    }
  }

  $shouldResetSchema = $isSqlite || $resetNonSqlite;
  $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
  if ($shouldResetSchema && is_string($kernelClass) && class_exists($kernelClass) && is_subclass_of($kernelClass, KernelInterface::class)) {
    /** @var KernelInterface $kernel */
    // Boot with debug=false for schema setup only - tests will recompile with their own settings
    $kernel = new $kernelClass($environment, false);
    $kernel->boot();

    /** @var ContainerInterface $container */
    $container = $kernel->getContainer();
    /** @var Doctrine\ORM\EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');

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
    $kernel->shutdown();
  }
}

if ($_SERVER['APP_DEBUG']) {
  umask(0000);
}

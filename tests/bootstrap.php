<?php

declare(strict_types=1);

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// Preserve APP_ENV if already set (e.g., by PHPUnit's server variables)
// This ensures test environment is used even when Infection randomizes test order
// PHPUnit sets APP_ENV via <server> tag BEFORE bootstrap runs
$appEnv = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;
if (is_string($appEnv) && '' !== $appEnv) {
  // Ensure both $_SERVER and $_ENV have the value so bootEnv() loads the correct .env.test file
  $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $appEnv;
  putenv("APP_ENV={$appEnv}");
}

(new Dotenv())->usePutenv(true)->bootEnv(dirname(__DIR__) . '/.env');

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
  $testToken = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;
  $tokenSuffix = (is_string($testToken) && '' !== $testToken) ? '-' . $testToken : '';
  $baseTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fireguard-auth' . $tokenSuffix;
  $environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'test';
  if (!is_string($environment) || '' === $environment) {
    $environment = 'test';
  }
  $cacheDir = $baseTempDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $environment;
  $logDir = $baseTempDir . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . $environment;

  $_SERVER['APP_CACHE_DIR'] = $_ENV['APP_CACHE_DIR'] = $cacheDir;
  $_SERVER['APP_LOG_DIR'] = $_ENV['APP_LOG_DIR'] = $logDir;

  $databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
  if (!is_string($databaseUrl)) {
    $databaseUrl = '';
  }
  if ('' === $databaseUrl || str_starts_with($databaseUrl, 'sqlite://')) {
    $dbPath = $baseTempDir . DIRECTORY_SEPARATOR . 'test.db';
    $normalizedPath = ltrim(str_replace('\\', '/', $dbPath), '/');
    $sqliteUrl = 'sqlite:///' . $normalizedPath;
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $sqliteUrl;
  }

  if (!is_dir($baseTempDir)) {
    mkdir($baseTempDir, 0777, true);
  }

  $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
  if (is_string($kernelClass) && class_exists($kernelClass) && is_subclass_of($kernelClass, KernelInterface::class)) {
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

    try {
      $schemaTool->dropSchema($metadata);
    } catch (Throwable) {
      // Schema might not exist yet
    }

    $schemaTool->createSchema($metadata);

    $entityManager->clear();
    $entityManager->getConnection()->close();
    $kernel->shutdown();

    // Clear the compiled container cache to ensure tests get a fresh container
    // with test.service_container available (compiled with framework.test: true)
    // The bootstrap boots with debug=false but tests need debug container
    if (is_dir($cacheDir)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
      );
      foreach ($iterator as $file) {
        if ($file->isDir()) {
          @rmdir($file->getPathname());
        } else {
          @unlink($file->getPathname());
        }
      }
    }
  }
}

if ($_SERVER['APP_DEBUG']) {
  umask(0000);
}

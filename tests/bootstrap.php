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
  }
}

if ($_SERVER['APP_DEBUG']) {
  umask(0000);
}

<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

use function is_string;

class Kernel extends BaseKernel
{
  use MicroKernelTrait;

  public function getCacheDir(): string
  {
    $cacheDir = $_SERVER['APP_CACHE_DIR'] ?? $_ENV['APP_CACHE_DIR'] ?? null;
    if (is_string($cacheDir) && '' !== $cacheDir) {
      return $cacheDir;
    }

    return parent::getCacheDir();
  }

  public function getLogDir(): string
  {
    $logDir = $_SERVER['APP_LOG_DIR'] ?? $_ENV['APP_LOG_DIR'] ?? null;
    if (is_string($logDir) && '' !== $logDir) {
      return $logDir;
    }

    return parent::getLogDir();
  }
}

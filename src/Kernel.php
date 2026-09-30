<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

use function is_string;

/**
 * Class Kernel
 *
 * Boots the Symfony application and supports isolated cache and log directories.
 *
 * @category Kernel
 */
class Kernel extends BaseKernel
{
  use MicroKernelTrait;

  // #region Methods
  /**
   * Method getCacheDir
   *
   * Uses a non-empty server or environment override, otherwise Symfony's default cache directory.
   *
   * @access public
   *
   * @return string the configured cache directory
   */
  public function getCacheDir(): string
  {
    $cacheDir = $_SERVER['APP_CACHE_DIR'] ?? $_ENV['APP_CACHE_DIR'] ?? null;
    if (is_string($cacheDir) && '' !== $cacheDir) {
      return $cacheDir;
    }

    return parent::getCacheDir();
  }

  /**
   * Method getLogDir
   *
   * Uses a non-empty server or environment override, otherwise Symfony's default log directory.
   *
   * @access public
   *
   * @return string the configured log directory
   */
  public function getLogDir(): string
  {
    $logDir = $_SERVER['APP_LOG_DIR'] ?? $_ENV['APP_LOG_DIR'] ?? null;
    if (is_string($logDir) && '' !== $logDir) {
      return $logDir;
    }

    return parent::getLogDir();
  }
  // #endregion
}

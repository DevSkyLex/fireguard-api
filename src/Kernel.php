<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\ContributionManifestConfigurationPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use User\Infrastructure\Symfony\DependencyInjection\PasswordPolicyConfigurationPass;

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

  // #region Methods
  /**
   * Method build
   *
   * Validates selected owner configuration before lazy services can defer invalid settings.
   *
   * @access protected
   *
   * @param ContainerBuilder $container the selected application composition
   *
   * @return void
   */
  protected function build(ContainerBuilder $container): void
  {
    parent::build($container);
    $container->addCompilerPass(new PasswordPolicyConfigurationPass());
    $container->addCompilerPass(new ContributionManifestConfigurationPass());
  }
  // #endregion
}

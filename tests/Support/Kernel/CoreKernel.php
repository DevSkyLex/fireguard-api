<?php

declare(strict_types=1);

namespace Tests\Support\Kernel;

use App\Kernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function basename;
use function dirname;
use function glob;

/**
 * Class CoreKernel
 *
 * Exercises the reusable composition against existing migrated PostgreSQL clones.
 * It selects modules only; it never synthesizes a replacement database history.
 *
 * @category TestSupport
 */
final class CoreKernel extends Kernel
{
  // #region Constants
  /**
   * Constant MODULES
   *
   * The deliberately exact core composition, independent of production imports.
   *
   * @var list<string>
   */
  public const array MODULES = ['Shared', 'Auth', 'OAuth', 'User', 'Session', 'Otp', 'TrustedDevice', 'Authorization', 'Tenant', 'Audit', 'Notification'];

  /**
   * Constant MODULE_FILES
   *
   * @var list<string>
   */
  private const array MODULE_FILES = ['shared', 'auth', 'oauth', 'user', 'session', 'otp', 'trusted_device', 'authorization', 'tenant', 'audit', 'notification'];
  // #endregion

  // #region Methods
  /**
   * Method getProjectDir
   *
   * Uses the real application tree so every selected module keeps its normal configuration.
   *
   * @access public
   *
   * @return string the API checkout
   */
  public function getProjectDir(): string
  {
    return dirname(__DIR__, 3);
  }

  /**
   * Method getCacheDir
   *
   * Separates this composition from the full kernel's fingerprinted test cache.
   *
   * @access public
   *
   * @return string the dedicated core cache
   */
  public function getCacheDir(): string
  {
    return parent::getCacheDir() . '/core';
  }

  /**
   * Method configureContainer
   *
   * Loads shared packages and exactly the chosen module-owned services and fragments.
   *
   * @access protected
   *
   * @param ContainerConfigurator $container the Symfony configuration loader
   *
   * @return void
   */
  protected function configureContainer(ContainerConfigurator $container): void
  {
    $config = $this->getProjectDir() . '/config';
    foreach (glob($config . '/packages/*.yaml') ?: [] as $package) {
      if ('modules.yaml' !== basename($package)) {
        $container->import($package);
      }
    }
    $container->import($config . '/packages/' . $this->environment . '/*.yaml');
    foreach (self::MODULE_FILES as $module) {
      $container->import($config . '/modules/' . $module . '.yaml');
    }
    $container->import($config . '/services.yaml');
    $container->import($config . '/services_' . $this->environment . '.yaml');
  }

  /**
   * Method configureRoutes
   *
   * API Platform discovers only selected resources; the business webhook route stays module-owned.
   *
   * @access protected
   *
   * @param RoutingConfigurator $routes the Symfony route loader
   *
   * @return void
   */
  protected function configureRoutes(RoutingConfigurator $routes): void
  {
    $config = $this->getProjectDir() . '/config/routes';
    foreach (['api_platform', 'framework', 'security'] as $route) {
      $routes->import($config . '/' . $route . '.yaml');
    }
  }
  // #endregion
}

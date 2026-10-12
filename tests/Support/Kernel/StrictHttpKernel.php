<?php

declare(strict_types=1);

namespace Tests\Support\Kernel;

use App\Kernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function dirname;

/**
 * Class StrictHttpKernel
 *
 * Exercises the full production module selection with the mandatory HTTP contract and dedicated public test keys.
 *
 * @category TestSupport
 */
final class StrictHttpKernel extends Kernel
{
  // #region Methods
  /**
   * Method getProjectDir
   *
   * @access public
   *
   * @return string the application checkout
   */
  public function getProjectDir(): string
  {
    return dirname(__DIR__, 3);
  }

  /**
   * Method getCacheDir
   *
   * @access public
   *
   * @return string the mandatory-contract test cache
   */
  public function getCacheDir(): string
  {
    return parent::getCacheDir() . '/strict-http';
  }

  /**
   * Method configureContainer
   *
   * Preserves every production module and overrides only the historical test compatibility flag.
   *
   * @access protected
   *
   * @param ContainerConfigurator $container the test composition
   *
   * @return void
   */
  protected function configureContainer(ContainerConfigurator $container): void
  {
    $config = $this->getProjectDir() . '/config';
    $container->import($config . '/packages/*.{php,yaml}');
    $container->import($config . '/packages/' . $this->environment . '/*.{php,yaml}');
    $container->import($config . '/services.yaml');
    $container->import($config . '/services_' . $this->environment . '.yaml');
    $container->parameters()->set('http_idempotency.required', true);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Shared\Application\Contract\Product\{EmailBrand, ProductIdentity};
use Shared\Application\Port\Outbound\ProductConfigPort;
use Shared\Infrastructure\Adapter\Product\ProductConfigAdapter;
use Symfony\Component\Yaml\Yaml;

use function array_replace;
use function dirname;
use function str_replace;

/**
 * Class ProductConfigFixture
 *
 * Exercises the shipped product defaults while permitting explicit deployment variants.
 *
 * @category Test
 */
final class ProductConfigFixture
{
  // #region Methods
  /**
   * Method parameters
   *
   * @access public
   *
   * @return array<string, string> the public product parameters with local paths resolved
   */
  public static function parameters(): array
  {
    $projectDir = dirname(__DIR__, 3);
    /** @var array{parameters: array<string, string>} $configuration */
    $configuration = Yaml::parseFile($projectDir . '/config/packages/product.yaml');
    $parameters = $configuration['parameters'];
    $parameters['product.email_mark_path'] = str_replace('%kernel.project_dir%', $projectDir, $parameters['product.email_mark_path']);
    $parameters['product.frontend_url'] = 'http://localhost:4200';

    return $parameters;
  }

  /**
   * Method create
   *
   * @access public
   *
   * @param array<string, string> $overrides explicit public deployment overrides
   *
   * @return ProductConfigPort the configured product adapter
   */
  public static function create(array $overrides = []): ProductConfigPort
  {
    $parameters = array_replace(self::parameters(), $overrides);

    return new ProductConfigAdapter(
      new ProductIdentity($parameters['product.public_name'], $parameters['product.api_title'], $parameters['product.api_description'], $parameters['product.version']),
      new EmailBrand($parameters['product.email_mark_path'], $parameters['product.email_mark_cid']),
      $parameters['product.frontend_url'],
      $parameters['product.assistant_instructions'],
    );
  }
  // #endregion
}

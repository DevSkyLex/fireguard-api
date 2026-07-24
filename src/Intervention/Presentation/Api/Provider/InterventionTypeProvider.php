<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Intervention\Presentation\Api\Dto\Output\InterventionTypeOutput;

use function array_map;

/**
 * Provider InterventionTypeProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionTypeOutput>
 */
final class InterventionTypeProvider implements ProviderInterface
{
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return list<InterventionTypeOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $definitions = [
      ['site_setup', 'Site setup', 'Declare or enrich a site and its physical hierarchy.', ['site_setup', 'inventory']],
      ['inventory', 'Inventory', 'Verify and complete equipment inventory for a site.', ['inventory']],
      ['inspection_campaign', 'Inspection campaign', 'Execute assigned inspections across a prepared scope.', ['inspection']],
    ];

    return array_map(static function (array $definition): InterventionTypeOutput {
      $output = new InterventionTypeOutput();
      [$output->id, $output->label, $output->description, $output->actions] = $definition;

      return $output;
    }, $definitions);
  }
}

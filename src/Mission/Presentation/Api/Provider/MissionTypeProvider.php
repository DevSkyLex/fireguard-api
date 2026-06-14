<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mission\Presentation\Api\Dto\Output\MissionTypeOutput;

use function array_map;

/**
 * Provider MissionTypeProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MissionTypeOutput>
 */
final class MissionTypeProvider implements ProviderInterface
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
   * @return list<MissionTypeOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $definitions = [
      ['site_setup', 'Site setup', 'Declare or enrich a site and its physical hierarchy.', ['site_setup', 'inventory']],
      ['inventory', 'Inventory', 'Verify and complete equipment inventory for a site.', ['inventory']],
      ['inspection_campaign', 'Inspection campaign', 'Execute assigned inspections across a prepared scope.', ['inspection']],
    ];

    return array_map(static function (array $definition): MissionTypeOutput {
      $output = new MissionTypeOutput();
      [$output->id, $output->label, $output->description, $output->actions] = $definition;

      return $output;
    }, $definitions);
  }
}

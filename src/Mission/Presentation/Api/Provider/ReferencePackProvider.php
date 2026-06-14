<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mission\Presentation\Api\Dto\Output\ReferencePackOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider ReferencePackProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ReferencePackOutput>
 */
final readonly class ReferencePackProvider implements ProviderInterface
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
   * @return ReferencePackOutput|array<int, ReferencePackOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ReferencePackOutput|array
  {
    $pack = new ReferencePackOutput();
    $pack->id = 'fr-erp-ert-v1';
    $pack->country = 'FR';
    $pack->regime = 'ERP_ERT';
    $pack->name = 'France ERP / ERT';
    $pack->version = '1.0.0';
    $pack->recommendedEquipmentTypes = ['fire_extinguisher', 'fire_alarm', 'emergency_lighting'];

    if (isset($uriVariables['id']) && 'fr-erp-ert-v1' !== $uriVariables['id']) {
      throw new NotFoundHttpException('Reference pack not found.');
    }

    return isset($uriVariables['id']) ? $pack : [$pack];
  }
}

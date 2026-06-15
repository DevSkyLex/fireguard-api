<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Intervention\Domain\Catalog\ReferencePackCatalog;
use Intervention\Domain\ValueObject\ReferencePack;
use Intervention\Presentation\Api\Dto\Output\ReferencePackOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function array_map;
use function is_string;

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
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ReferencePackCatalog $catalog the reference pack catalog
   */
  public function __construct(private ReferencePackCatalog $catalog)
  {
  }

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
    if (isset($uriVariables['id'])) {
      $id = is_string($uriVariables['id']) ? $uriVariables['id'] : '';
      $pack = $this->catalog->find($id);
      if (!$pack instanceof ReferencePack) {
        throw new NotFoundHttpException('Reference pack not found.');
      }

      return $this->toOutput($pack);
    }

    return array_map($this->toOutput(...), $this->catalog->all());
  }

  /**
   * Method toOutput.
   *
   * Maps a reference pack value object to its API output representation.
   *
   * @since 1.0.0
   *
   * @param ReferencePack $pack the reference pack value
   *
   * @return ReferencePackOutput the output result
   */
  private function toOutput(ReferencePack $pack): ReferencePackOutput
  {
    $output = new ReferencePackOutput();
    $output->id = $pack->id;
    $output->country = $pack->country;
    $output->regime = $pack->regime;
    $output->name = $pack->name;
    $output->version = $pack->version;
    $output->recommendedEquipmentTypes = $pack->recommendedEquipmentTypes;

    return $output;
  }
}

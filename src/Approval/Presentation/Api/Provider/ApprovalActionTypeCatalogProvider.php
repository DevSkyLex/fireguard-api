<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Presentation\Api\Dto\Output\ApprovalActionTypeOutput;

use function array_map;

/**
 * Provider ApprovalActionTypeCatalogProvider.
 *
 * Static reference catalog (`GET /approvals/action-types`) for the settings
 * UI. Kept thin: it reads directly from `ApprovalActionTypes`, a static
 * registry, per ARCHITECTURE.md's reference-catalog guideline.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ApprovalActionTypeOutput>
 */
final readonly class ApprovalActionTypeCatalogProvider implements ProviderInterface
{
  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return list<ApprovalActionTypeOutput> the reference catalog rows
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    return array_map(self::toOutput(...), ApprovalActionTypes::all());
  }

  /**
   * Method toOutput.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $value the action type value
   *
   * @return ApprovalActionTypeOutput the mapped output
   */
  private static function toOutput(string $value): ApprovalActionTypeOutput
  {
    $output = new ApprovalActionTypeOutput();
    $output->value = $value;
    $output->label = ApprovalActionTypes::label($value);

    return $output;
  }
  // #endregion
}

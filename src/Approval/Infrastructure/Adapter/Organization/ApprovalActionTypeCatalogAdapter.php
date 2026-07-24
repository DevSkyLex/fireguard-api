<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\Organization;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use Organization\Application\Port\Outbound\ApprovalActionTypeCatalogPort;

use function array_map;

/**
 * Adapter ApprovalActionTypeCatalogAdapter.
 *
 * Implements the Organization module's approval action-type catalog port on
 * top of {@see ApprovalActionTypes}, keeping the Approval module's contract
 * types private to Infrastructure boundaries — mirrors
 * {@see \Equipment\Infrastructure\Adapter\Organization\EquipmentTypeCatalogAdapter}.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalActionTypeCatalogAdapter implements ApprovalActionTypeCatalogPort
{
  // #region Methods
  /**
   * Method values.
   *
   * @since 1.0.0
   *
   * @return list<string> the action type values
   */
  public function values(): array
  {
    return ApprovalActionTypes::all();
  }

  /**
   * Method descriptors.
   *
   * @since 1.0.0
   *
   * @return list<array{value: string, label: string}> the action type descriptors
   */
  public function descriptors(): array
  {
    return array_map(
      static fn (string $value): array => ['value' => $value, 'label' => ApprovalActionTypes::label($value)],
      ApprovalActionTypes::all(),
    );
  }
  // #endregion
}

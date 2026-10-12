<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\Organization;

use Approval\Application\Contract\Action\ApprovalActionDescriptor;
use Approval\Application\Port\Outbound\ApprovalActionCatalogPort;
use Organization\Application\Port\Outbound\ApprovalActionTypeCatalogPort;

use function array_map;

/**
 * Adapter ApprovalActionTypeCatalogAdapter.
 *
 * Implements the Organization module's approval action-type catalog port on
 * top of the installed action catalog. The boundary exchanges public metadata
 * without exposing execution services to Organization.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalActionTypeCatalogAdapter implements ApprovalActionTypeCatalogPort
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param ApprovalActionCatalogPort $catalog the installed action metadata
   */
  public function __construct(private ApprovalActionCatalogPort $catalog)
  {
  }
  // #endregion

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
    return array_map(static fn (ApprovalActionDescriptor $descriptor): string => $descriptor->value, $this->catalog->descriptors());
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
      static fn (ApprovalActionDescriptor $descriptor): array => ['value' => $descriptor->value, 'label' => $descriptor->label],
      $this->catalog->descriptors(),
    );
  }
  // #endregion
}

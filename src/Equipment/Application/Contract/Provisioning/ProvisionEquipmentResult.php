<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Provisioning;

/**
 * Contract ProvisionEquipmentResult.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProvisionEquipmentResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ProvisionOutcome $outcome the provisioning outcome
   * @param ?string $resourceId the created equipment identifier, when `CREATED`
   * @param ?string $message the failure reason, when not `CREATED`
   */
  public function __construct(
    public ProvisionOutcome $outcome,
    public ?string $resourceId = null,
    public ?string $message = null,
  ) {
  }
  // #endregion
}

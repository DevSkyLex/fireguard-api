<?php

declare(strict_types=1);

namespace Import\Application\Support;

use function in_array;

/**
 * Support DryRunProjection.
 *
 * Running, in-memory state `ProcessImportJobHandler` threads through a
 * dry-run batch as it walks the CSV file row by row: how many rows have
 * already been reported `would_create` for each resource kind (the
 * quota-projection offset a provisioning port needs to answer "would this
 * row, plus everything already counted in this same batch, exceed the
 * cap"), and — facility imports only — the `code` of every row reported
 * `would_create` so far, so a child row's `parentCode` can resolve against a
 * parent that would itself be created earlier in the same file rather than
 * only against what is already in the database. A real (write) run never
 * builds one: rows are provisioned as they are read, so the database itself
 * carries this state.
 *
 * @category Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DryRunProjection
{
  // #region Properties
  /**
   * Property equipmentCount.
   *
   * @since 1.0.0
   */
  private int $equipmentCount = 0;

  /**
   * Property facilityCount.
   *
   * @since 1.0.0
   */
  private int $facilityCount = 0;

  /**
   * Property facilityPendingCodes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private array $facilityPendingCodes = [];
  // #endregion

  // #region Methods
  /**
   * Method equipmentCount.
   *
   * @since 1.0.0
   */
  public function equipmentCount(): int
  {
    return $this->equipmentCount;
  }

  /**
   * Method recordEquipmentWouldCreate.
   *
   * @since 1.0.0
   */
  public function recordEquipmentWouldCreate(): void
  {
    ++$this->equipmentCount;
  }

  /**
   * Method facilityCount.
   *
   * @since 1.0.0
   */
  public function facilityCount(): int
  {
    return $this->facilityCount;
  }

  /**
   * Method facilityPendingCodes.
   *
   * @since 1.0.0
   *
   * @return list<string> the codes of rows would-created earlier in this batch
   */
  public function facilityPendingCodes(): array
  {
    return $this->facilityPendingCodes;
  }

  /**
   * Method recordFacilityWouldCreate.
   *
   * @since 1.0.0
   *
   * @param ?string $code the row's own code, when it has one
   */
  public function recordFacilityWouldCreate(?string $code): void
  {
    ++$this->facilityCount;

    if (null !== $code && !in_array($code, $this->facilityPendingCodes, true)) {
      $this->facilityPendingCodes[] = $code;
    }
  }
  // #endregion
}

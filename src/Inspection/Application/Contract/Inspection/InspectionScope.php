<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Contract InspectionScope.
 *
 * The two ownership facts a canonical mutation has to check before tying a
 * row to an inspection: which organization the inspection belongs to, and
 * which intervention — if any — prepared it.
 *
 * `interventionId` is the reason this exists at all. It is a canonical
 * offline-sync column carried by `InspectionRecord` and deliberately absent
 * from the `Inspection` aggregate, so `InspectionRepositoryPort::findById()`
 * cannot answer it.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionScope
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the inspection identifier
   * @param string $organizationId the owning organization identifier
   * @param ?string $interventionId the preparing intervention identifier, when any
   */
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public ?string $interventionId,
  ) {
  }
  // #endregion
}

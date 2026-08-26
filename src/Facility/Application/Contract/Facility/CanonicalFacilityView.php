<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Facility;

/**
 * Contract CanonicalFacilityView.
 *
 * Everything `CanonicalFacilityMutationProcessor` needs *before* it may
 * dispatch: the organization to permission-check against, the record status
 * and intervention that decide WHICH permission, and the revision `If-Match`
 * is compared to.
 *
 * Deliberately not the whole row. The processor answers with
 * `CanonicalFacilityProvider`'s output, which joins counts and ancestry this
 * projection has no business carrying.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalFacilityView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the facility identifier
   * @param string $organizationId the owning organization identifier
   * @param string $recordStatus `draft` for an intervention scratchpad, `published` otherwise
   * @param ?string $interventionId the preparing intervention identifier
   * @param int $revision the optimistic-concurrency revision
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $recordStatus,
    public ?string $interventionId,
    public int $revision,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Contract CanonicalInspectionView.
 *
 * Everything `CanonicalInspectionMutationProcessor` needs *before* it may
 * dispatch: the organization to permission-check against, the record status
 * and intervention that decide WHICH permission, and the revision `If-Match`
 * is compared to.
 *
 * Deliberately not the whole row. The processor answers with
 * `CanonicalInspectionProvider`'s output, which joins equipment and facility
 * names this projection has no business carrying.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalInspectionView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the inspection identifier
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

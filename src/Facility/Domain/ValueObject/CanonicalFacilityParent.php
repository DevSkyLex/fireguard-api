<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * ValueObject CanonicalFacilityParent.
 *
 * The proposed parent of a re-parenting, already resolved and already
 * validated by the handler: it exists, it belongs to the same organization,
 * it is not a descendant of the facility being moved, and the resulting
 * sub-tree fits under the depth cap.
 *
 * The model still needs its `status`, because restoring a facility under an
 * archived parent is refused — and that check reads the NEW parent, not the
 * old one.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalFacilityParent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the parent identifier
   * @param FacilityStatus $status the parent lifecycle status
   */
  public function __construct(
    public string $id,
    public FacilityStatus $status,
  ) {
  }
  // #endregion
}

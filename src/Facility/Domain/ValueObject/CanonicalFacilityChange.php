<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * ValueObject CanonicalFacilityChange.
 *
 * What one canonical PATCH actually changed, which is not the same thing as
 * what its body carried: resending the current value must stay a no-op, and
 * a same-parent move must emit nothing.
 *
 * Every field is empty for a DRAFT scratchpad row — those edits are a
 * preparation, never a compliance event.
 *
 * `changedFields` lists DESCRIPTIVE fields only. Status and parent are
 * reported through `archived` / `restored` / the parent pair, and never
 * duplicated here.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalFacilityChange
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $archived whether a published facility moved to `archived`
   * @param bool $restored whether a published facility moved from `archived` to `active`
   * @param bool $parentMoved whether the parent identifier actually differs
   * @param ?string $previousParentFacilityId the parent before the patch
   * @param ?string $newParentFacilityId the parent after the patch
   * @param list<string> $changedFields the descriptive fields whose value actually differs
   */
  public function __construct(
    public bool $archived = false,
    public bool $restored = false,
    public bool $parentMoved = false,
    public ?string $previousParentFacilityId = null,
    public ?string $newParentFacilityId = null,
    public array $changedFields = [],
  ) {
  }
  // #endregion
}

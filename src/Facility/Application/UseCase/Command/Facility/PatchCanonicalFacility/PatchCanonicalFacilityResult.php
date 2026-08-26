<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase PatchCanonicalFacilityResult.
 *
 * Reports what the patch actually changed, which is not the same as what its
 * body carried: resending the current value stays a no-op, and a same-parent
 * move emits nothing. Every flag is false and `changedFields` empty for a
 * scratchpad row.
 *
 * The processor does not read this; it is asserted by the handler tests and
 * kept because a Result that hides why an event fired is a Result nobody can
 * test.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalFacilityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param list<string> $changedFields the descriptive fields whose value actually differs
   */
  public function __construct(
    public string $facilityId,
    public int $revision,
    public bool $archived = false,
    public bool $restored = false,
    public bool $parentMoved = false,
    public array $changedFields = [],
  ) {
  }
  // #endregion
}

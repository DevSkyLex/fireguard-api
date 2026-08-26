<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception CanonicalInspectionValidationException.
 *
 * A well-formed canonical PATCH whose *content* the lifecycle refuses: a
 * non-nullable field explicitly sent as null, or a status jump the aggregate
 * lifecycle does not allow. Mapped to 422 in
 * `config/packages/api_platform.yaml`.
 *
 * **422, not 409, and that is inherited rather than chosen.**
 * `CanonicalInspectionMutationProcessor` answered 422 for an illegal
 * transition where the `Inspection` aggregate answers 409 for the same
 * situation (`InspectionNotSubmittedException` on a `draft → closed` jump
 * through `POST …/close`). The two surfaces disagree, they disagreed before
 * this refactor, and reconciling them changes a published status — a product
 * decision, not a side effect. See `src/Inspection/MODULE.md`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalInspectionValidationException extends RuntimeException
{
  // #region Methods
  /**
   * Method fieldCannotBeNull.
   *
   * @since 1.0.0
   *
   * @param string $field the merge-patch field explicitly sent as null
   *
   * @return self the exception instance
   */
  public static function fieldCannotBeNull(string $field): self
  {
    return new self(sprintf('Inspection %s cannot be null.', $field));
  }

  /**
   * Method unsupportedValue.
   *
   * A non-null value outside the enum. Unreachable over HTTP —
   * `PatchCanonicalInspectionInput` carries `#[Assert\Choice]` on both fields,
   * so API Platform rejects it first — but a direct dispatch on the command
   * bus can reach it, and answering 422 beats persisting the string.
   *
   * @since 1.0.0
   *
   * @param string $field the merge-patch field
   * @param string $value the rejected value
   *
   * @return self the exception instance
   */
  public static function unsupportedValue(string $field, string $value): self
  {
    return new self(sprintf('Inspection %s "%s" is not a supported value.', $field, $value));
  }

  /**
   * Method illegalStatusTransition.
   *
   * @since 1.0.0
   *
   * @param string $from the current status
   * @param string $to the requested status
   *
   * @return self the exception instance
   */
  public static function illegalStatusTransition(string $from, string $to): self
  {
    return new self(sprintf('Illegal inspection status transition from %s to %s.', $from, $to));
  }
  // #endregion
}

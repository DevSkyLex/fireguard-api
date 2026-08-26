<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception CanonicalFacilityValidationException.
 *
 * A well-formed canonical PATCH whose *content* the hierarchy or the
 * lifecycle refuses. Mapped to 422 in `config/packages/api_platform.yaml` —
 * the status `CanonicalFacilityMutationProcessor` emitted for every one of
 * these conditions.
 *
 * Note that the depth message is borrowed from `FacilityHierarchyException`,
 * which is itself mapped to **400** elsewhere. The canonical surface answered
 * 422 for it, by wrapping the message rather than the exception, and keeps
 * doing so.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalFacilityValidationException extends RuntimeException
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
    return new self(sprintf('Facility %s cannot be null.', $field));
  }

  /**
   * Method unsupportedValue.
   *
   * A non-null value outside the enum. Unreachable over HTTP —
   * `PatchCanonicalFacilityInput` carries `#[Assert\Choice]` on both `type`
   * and `status` — but a direct dispatch on the command bus can reach it.
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
    return new self(sprintf('Facility %s "%s" is not a supported value.', $field, $value));
  }

  /**
   * Method coordinatesMustBePaired.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function coordinatesMustBePaired(): self
  {
    return new self('Facility latitude and longitude must be provided together.');
  }

  /**
   * Method invalidParent.
   *
   * The proposed parent does not exist, or belongs to another organization.
   * One message for both on purpose: distinguishing them would tell a caller
   * whether a facility id exists in a tenant they cannot see.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function invalidParent(): self
  {
    return new self('Parent facility is invalid.');
  }

  /**
   * Method parentWouldCreateACycle.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function parentWouldCreateACycle(): self
  {
    return new self('Parent facility would create a hierarchy cycle.');
  }

  /**
   * Method parentIsArchived.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function parentIsArchived(): self
  {
    return new self('Parent facility is archived.');
  }

  /**
   * Method cannotRestoreUnderAnArchivedParent.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotRestoreUnderAnArchivedParent(): self
  {
    return new self('Cannot restore a facility while its parent is archived.');
  }

  /**
   * Method maxDepthExceeded.
   *
   * Reuses `FacilityHierarchyException`'s wording verbatim: the processor
   * wrapped that exception's MESSAGE in a 422 rather than letting the
   * exception itself surface as its own 400.
   *
   * @since 1.0.0
   *
   * @param int $cap the configured depth cap
   *
   * @return self the exception instance
   */
  public static function maxDepthExceeded(int $cap): self
  {
    return new self(FacilityHierarchyException::maxDepthExceeded($cap)->getMessage());
  }
  // #endregion
}

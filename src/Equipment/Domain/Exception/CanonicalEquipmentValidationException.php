<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception CanonicalEquipmentValidationException.
 *
 * A well-formed canonical PATCH whose *content* the lifecycle refuses.
 * Mapped to 422 in `config/packages/api_platform.yaml` — the status
 * `CanonicalEquipmentMutationProcessor` emitted for all five conditions
 * below before they moved into the domain.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalEquipmentValidationException extends RuntimeException
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
    return new self(sprintf('Equipment %s cannot be null.', $field));
  }

  /**
   * Method unsupportedValue.
   *
   * A non-null value outside the enum. Unreachable over HTTP —
   * `PatchCanonicalEquipmentInput` carries `#[Assert\Choice]` on `status` —
   * but a direct dispatch on the command bus can reach it, and answering 422
   * beats persisting the string.
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
    return new self(sprintf('Equipment %s "%s" is not a supported value.', $field, $value));
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
    return new self(sprintf('Illegal equipment status transition from %s to %s.', $from, $to));
  }

  /**
   * Method facilityOutsideOrganization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function facilityOutsideOrganization(): self
  {
    return new self('Facility must belong to the same organization.');
  }

  /**
   * Method inServiceRequiresFacility.
   *
   * Clearing the facility of operational or under-maintenance equipment would
   * strand it in an illegal state — and, under maintenance, leak its open
   * maintenance log. The caller must return it to stock first, which closes
   * any open log.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function inServiceRequiresFacility(): self
  {
    return new self('In-service equipment must be assigned to a facility.');
  }
  // #endregion
}

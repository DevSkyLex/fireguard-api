<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function implode;
use function sprintf;

/**
 * Exception FacilityMetadataValidationException.
 *
 * Raised by {@see \Facility\Application\Service\FacilityMetadataSchemaGuard}
 * when one or more facility metadata entries fail the organization's typed
 * schema (a value that does not parse as the definition's field type, or a
 * required field missing on create). Carries the offending keys so the API
 * boundary can report them together rather than one at a time. Mapped
 * centrally to HTTP 422 by
 * {@see \Facility\Presentation\Api\EventSubscriber\FacilityMetadataValidationExceptionSubscriber}
 * because it can be raised from three different write paths (the create/
 * update handlers, the canonical PATCH processor, and the offline
 * intervention apply path) that must all resolve to the same status.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataValidationException extends RuntimeException
{
  // #region Properties
  /**
   * @var list<string>
   */
  private array $offendingKeys;
  // #endregion

  // #region Methods
  /**
   * Method withOffendingKeys.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param list<string> $offendingKeys the metadata keys that failed validation
   *
   * @return self the exception instance
   */
  public static function withOffendingKeys(array $offendingKeys): self
  {
    $exception = new self(sprintf(
      'Facility metadata is invalid for key(s): %s.',
      implode(', ', $offendingKeys),
    ));
    $exception->offendingKeys = $offendingKeys;

    return $exception;
  }

  /**
   * Method offendingKeys.
   *
   * @since 1.0.0
   *
   * @return list<string> the metadata keys that failed validation
   */
  public function offendingKeys(): array
  {
    return $this->offendingKeys;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception InspectionResponseClientIdAlreadyExistsException.
 *
 * An offline client replayed a creation whose `clientId` is already stored.
 *
 * Deliberately ABSENT from `api_platform.exception_to_status`: this condition
 * answers 412 when the id came from the `PUT /inspection-responses/{id}` URI
 * and 409 when it came from the POST body, and that choice is HTTP shape, not
 * domain state. `InspectionResponseProcessor` catches this one exception and
 * rethrows `ClientResourceAlreadyExistsHttpException` with the status the
 * request shape calls for — the only catch left in that processor.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseClientIdAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withClientId.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client identifier
   *
   * @return self the exception instance
   */
  public static function withClientId(string $clientId): self
  {
    return new self('A resource with this client identifier already exists: ' . $clientId . '.');
  }
  // #endregion
}

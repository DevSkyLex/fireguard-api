<?php

declare(strict_types=1);

namespace Audit\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Class AuditEventNotFoundException
 *
 * Signals that an audit event could not be found by its identifier.
 *
 * @category Exception
 */
final class AuditEventNotFoundException extends EntityNotFoundException
{
  /**
   * Method withId.
   *
   * Creates the not-found exception for an audit event identifier.
   *
   * @access public
   *
   * @static
   *
   * @param string $id the audit event identifier
   *
   * @return self the not-found exception
   */
  public static function withId(string $id): self
  {
    return new self('AuditEvent with ID "' . $id . '" not found.');
  }
}

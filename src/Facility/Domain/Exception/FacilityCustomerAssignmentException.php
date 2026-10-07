<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/** Class FacilityCustomerAssignmentException. Customer ownership is only stored on root sites. @category Exception */
final class FacilityCustomerAssignmentException extends RuntimeException
{
  public static function rootRequired(): self
  {
    return new self('Only root sites can be assigned to a customer.');
  }
}

<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Class FacilityCustomerScopeNotFoundException
 *
 * Keeps unknown and foreign customer filters indistinguishable.
 *
 * @category Exception
 */
final class FacilityCustomerScopeNotFoundException extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('Customer not found.');
  }
}

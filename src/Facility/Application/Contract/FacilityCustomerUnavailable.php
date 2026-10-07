<?php

declare(strict_types=1);

namespace Facility\Application\Contract;

use RuntimeException;

/** Class FacilityCustomerUnavailable. Uniform refusal for missing, foreign or archived customer assignments. @category Contract */
final class FacilityCustomerUnavailable extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('Customer must be active and belong to this organization.');
  }
}

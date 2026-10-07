<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract\Source;

use RuntimeException;

/**
 * Class ServiceRequestOriginUnavailable
 *
 * Keeps unknown, foreign, unpublished and mismatched origin evidence indistinguishable.
 *
 * @category Contract
 */
final class ServiceRequestOriginUnavailable extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('The origin evidence is unavailable for this repair target.');
  }
}

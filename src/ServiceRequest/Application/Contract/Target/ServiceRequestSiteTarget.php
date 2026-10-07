<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract\Target;

/**
 * Class ServiceRequestSiteTarget
 *
 * Retains the root site and internal customer identity used to describe a request.
 *
 * @category Contract
 */
final readonly class ServiceRequestSiteTarget
{
  /**
   * @param ?array{id:string,name:string} $customer
   */
  public function __construct(public string $id, public string $name, public bool $archived, public ?array $customer)
  {
  }
}

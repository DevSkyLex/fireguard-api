<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\CreateServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Shared\Application\Message\ResultMessage;

/** Class CreateServiceRequestResult. Persisted request projection. @category Result */
final readonly class CreateServiceRequestResult implements ResultMessage
{
  public function __construct(public ServiceRequestView $request)
  {
  }
}

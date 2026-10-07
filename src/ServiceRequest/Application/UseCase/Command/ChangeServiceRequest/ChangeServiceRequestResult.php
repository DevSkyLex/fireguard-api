<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ChangeServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Shared\Application\Message\ResultMessage;

/** Class ChangeServiceRequestResult. Persisted request projection. @category Result */
final readonly class ChangeServiceRequestResult implements ResultMessage
{
  public function __construct(public ServiceRequestView $request)
  {
  }
}

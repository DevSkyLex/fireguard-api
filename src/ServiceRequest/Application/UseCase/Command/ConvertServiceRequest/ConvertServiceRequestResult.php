<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\ConvertServiceRequest;

use ServiceRequest\Application\Contract\ServiceRequestView;
use Shared\Application\Message\ResultMessage;

/** Class ConvertServiceRequestResult. Persisted request projection. @category Result */
final readonly class ConvertServiceRequestResult implements ResultMessage
{
  public function __construct(public ServiceRequestView $request)
  {
  }
}

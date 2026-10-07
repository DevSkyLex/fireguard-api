<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class ConvertServiceRequestInput. Validated repair request transport fields. @category Input */
final class ConvertServiceRequestInput
{
  /**
   * Property clientOperationId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $clientOperationId = '';

  /**
   * Property existingInterventionId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $existingInterventionId = null;

  /**
   * Property existingTaskId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $existingTaskId = null;
}

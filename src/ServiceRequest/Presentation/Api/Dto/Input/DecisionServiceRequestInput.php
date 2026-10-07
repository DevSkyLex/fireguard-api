<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class DecisionServiceRequestInput. Validated repair request transport fields. @category Input */
final class DecisionServiceRequestInput
{
  /**
   * Property reason.
   */
  #[Groups(['service_request:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 2000)]
  public string $reason = '';
}

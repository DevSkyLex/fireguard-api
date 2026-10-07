<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class UpdateServiceRequestInput. Validated repair request transport fields. @category Input */
final class UpdateServiceRequestInput
{
  /**
   * Property title.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Length(max: 160)]
  public ?string $title = null;

  /**
   * Property description.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Length(max: 10000)]
  public ?string $description = null;

  /**
   * Property priority.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public ?string $priority = null;
}

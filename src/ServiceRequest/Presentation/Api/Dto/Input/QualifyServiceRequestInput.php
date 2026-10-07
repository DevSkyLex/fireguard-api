<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class QualifyServiceRequestInput. Validated repair request transport fields. @category Input */
final class QualifyServiceRequestInput
{
  /**
   * Property equipmentId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $equipmentId = null;

  /**
   * Property note.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Length(max: 10000)]
  public ?string $note = null;
}

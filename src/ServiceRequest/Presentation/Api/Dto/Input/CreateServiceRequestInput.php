<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class CreateServiceRequestInput. Validated repair request transport fields. @category Input */
final class CreateServiceRequestInput
{
  /**
   * Property equipmentId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $equipmentId = null;

  /**
   * Property siteId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $siteId = null;

  /**
   * Property title.
   */
  #[Groups(['service_request:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 160)]
  public string $title = '';

  /**
   * Property description.
   */
  #[Groups(['service_request:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 10000)]
  public string $description = '';

  /**
   * Property priority.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public string $priority = 'normal';

  /**
   * Property originInspectionId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $originInspectionId = null;

  /**
   * Property originNonConformityId.
   */
  #[Groups(['service_request:write'])]
  #[Assert\Uuid]
  public ?string $originNonConformityId = null;
}

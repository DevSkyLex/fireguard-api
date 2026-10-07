<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Command\CreateServiceRequest;

use Shared\Application\Message\CommandMessage;

/** Class CreateServiceRequestCommand. Records a scoped repair need. @category Command */
final readonly class CreateServiceRequestCommand implements CommandMessage
{
  public function __construct(public string $actorId, public string $organizationId, public ?string $equipmentId, public ?string $siteId, public string $title, public string $description, public string $priority = 'normal', public ?string $originInspectionId = null, public ?string $originNonConformityId = null)
  {
  }
}

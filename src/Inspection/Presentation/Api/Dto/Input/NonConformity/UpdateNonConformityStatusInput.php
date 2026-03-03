<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\NonConformity;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\NonConformityStatus;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNonConformityStatusInput
{
  #[Assert\NotBlank(message: 'Status is required.')]
  #[Assert\Choice(callback: [NonConformityStatus::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'New non-conformity status', required: true, example: 'done')]
  public string $status = '';
}

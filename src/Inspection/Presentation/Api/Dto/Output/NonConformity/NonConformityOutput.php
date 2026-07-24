<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\NonConformity;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

final class NonConformityOutput
{
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $inspectionId = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $description = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $severity = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $dueAt = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $resolvedAt = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $notes = null;

  /**
   * Property equipmentId.
   *
   * Identifier of the equipment the parent inspection was performed on.
   * Populated only by listings that resolve it (the organization-wide
   * non-conformity collection); null on the per-inspection endpoints, which
   * already carry the inspection context the frontend used to get here.
   *
   * @since 1.2.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Identifier of the inspected equipment')]
  public ?string $equipmentId = null;

  /**
   * Property equipmentSerialNumber.
   *
   * Serial number of the inspected equipment, resolved through the
   * Equipment module's naming port for the same reason
   * {@see \Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput::$equipmentSerialNumber}
   * exists: a UUID names nothing to the agent standing in front of the
   * device. Null when unresolved, or when the endpoint does not resolve it.
   *
   * @since 1.2.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Serial number of the inspected equipment')]
  public ?string $equipmentSerialNumber = null;

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
}

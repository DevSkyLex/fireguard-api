<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\{InspectionResult, InspectorType};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInspectionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInspectionInput
{
  /**
   * Property clientId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Client ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Client-generated UUID for idempotent offline creation', required: false)]
  public ?string $clientId = null;

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '#^/api/organizations/[0-9a-fA-F-]{36}$#', message: 'Organization must be a valid organization IRI.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization IRI, required on the canonical endpoint', required: false)]
  public ?string $organization = null;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '#^/api/interventions/[0-9a-fA-F-]{36}$#', message: 'Intervention must be a valid intervention IRI.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional field intervention IRI', required: false)]
  public ?string $intervention = null;

  /**
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Equipment ID is required.')]
  #[Assert\Uuid(message: 'Equipment ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Equipment identifier', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $equipmentId = '';

  /**
   * Property result.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Result is required.')]
  #[Assert\Choice(callback: [InspectionResult::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspection result', required: true, example: 'pass')]
  public string $result = '';

  /**
   * Property performedAt.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Performed at date is required.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Date the inspection was performed (ISO 8601)', required: true, example: '2024-06-15T10:00:00+02:00')]
  public string $performedAt = '';

  /**
   * Property inspectorType.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Inspector type is required.')]
  #[Assert\Choice(callback: [InspectorType::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspector type (user or external)', required: true, example: 'user')]
  public string $inspectorType = '';

  /**
   * Property inspectorName.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Inspector name is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspector display name', required: true, example: 'Jean Dupont')]
  public string $inspectorName = '';

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Facility ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility identifier', required: false)]
  public ?string $facilityId = null;

  /**
   * Property checklistId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Checklist ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional checklist identifier', required: false)]
  public ?string $checklistId = null;

  /**
   * Property inspectorUserId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Inspector user ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional user identifier (required for user type)', required: false)]
  public ?string $inspectorUserId = null;

  /**
   * Property inspectorOrganizationName.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional external organization name', required: false)]
  public ?string $inspectorOrganizationName = null;

  /**
   * Property notes.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form notes', required: false)]
  public ?string $notes = null;

  /**
   * Property signature.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional signature data', required: false)]
  public ?string $signature = null;
}

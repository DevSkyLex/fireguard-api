<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use ApiPlatform\Metadata\ApiProperty;
use Facility\Domain\ValueObject\FacilityType;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateFacilityInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateFacilityInput
{
  /**
   * Property clientId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Client ID must be a valid UUID.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Client-generated UUID for idempotent offline creation', required: false)]
  public ?string $clientId = null;

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '#^/api/organizations/[0-9a-fA-F-]{36}$#', message: 'Organization must be a valid organization IRI.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Organization IRI, required on the canonical endpoint', required: false)]
  public ?string $organization = null;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Assert\Regex(pattern: '#^/api/interventions/[0-9a-fA-F-]{36}$#', message: 'Intervention must be a valid intervention IRI.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional field intervention IRI', required: false)]
  public ?string $intervention = null;

  // #region Properties
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility type is required.')]
  #[Assert\Choice(callback: [FacilityType::class, 'values'])]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Facility type', required: true, example: 'site')]
  public string $type = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Facility name is required.')]
  #[Assert\Length(min: 2, max: 120)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Facility display name', required: true, example: 'Headquarters')]
  public string $name = '';

  /**
   * Property parentFacilityId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(allowNull: true, message: 'Parent facility ID cannot be blank.')]
  #[Assert\Uuid(message: 'Parent facility ID must be a valid UUID.')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional parent facility identifier', required: false, example: '550e8400-e29b-41d4-a716-446655440001')]
  public ?string $parentFacilityId = null;

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 80)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility code', required: false, example: 'SITE-PAR-001')]
  public ?string $code = null;

  /**
   * Property address.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional address', required: false, example: '10 rue de Rivoli, Paris')]
  public ?string $address = null;

  /**
   * Property latitude.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: -90, max: 90)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional latitude, required together with longitude', required: false, example: 48.8566)]
  public ?float $latitude = null;

  /**
   * Property longitude.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: -180, max: 180)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional longitude, required together with latitude', required: false, example: 2.3522)]
  public ?float $longitude = null;

  /**
   * Property metadata.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[Assert\Type(type: 'array')]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form metadata', required: false, example: ['country' => 'FR', 'timezone' => 'Europe/Paris'])]
  public array $metadata = [];

  /**
   * Property levelIndex.
   *
   * @since 1.3.0
   */
  #[Assert\Range(min: -100, max: 200)]
  #[Groups([FacilitySerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional stacking order of the floor (ground floor = 0, first basement = -1)', required: false, example: 0)]
  public ?int $levelIndex = null;
  // #endregion
}

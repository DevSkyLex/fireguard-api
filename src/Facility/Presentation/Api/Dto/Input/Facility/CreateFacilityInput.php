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
  // #endregion
}

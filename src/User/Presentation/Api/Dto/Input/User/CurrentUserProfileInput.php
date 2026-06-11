<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Input\User;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO CurrentUserProfileInput.
 *
 * Contains only the profile fields an authenticated user may update
 * through the self-service API.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CurrentUserProfileInput
{
  // #region Properties
  /**
   * Property firstName.
   *
   * The first name.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank(allowNull: true)]
  #[Assert\Length(max: 100)]
  #[ApiProperty(
    description: 'User first name.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'Jane',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
  )]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * The last name.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank(allowNull: true)]
  #[Assert\Length(max: 100)]
  #[ApiProperty(
    description: 'User last name.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'Doe',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
  )]
  public ?string $lastName = null;
  // #endregion
}

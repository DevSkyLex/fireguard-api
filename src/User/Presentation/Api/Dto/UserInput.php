<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserInput
 * @final
 *
 * DTO for User Input (Create/Update).
 *
 * @category DTO
 * @package User\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInput
{
  //#region Properties
  /**
   * Property username
   *
   * The username.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 50)]
  public ?string $username = null;

  /**
   * Property email
   *
   * The user email.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Email]
  public ?string $email = null;

  /**
   * Property password
   *
   * The user password.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(min: 8)]
  public ?string $password = null;

  /**
   * Property firstName
   *
   * The first name.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  public ?string $firstName = null;

  /**
   * Property lastName
   *
   * The last name.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  public ?string $lastName = null;

  /**
   * Property avatarUrl
   *
   * The avatar URL.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  public ?string $avatarUrl = null;

  /**
   * Property tenantId
   *
   * The tenant ID.
   *
   * @var string|null
   */
  #[Groups([UserSerializationGroup::WRITE])]
  public ?string $tenantId = null;
  //#endregion
}

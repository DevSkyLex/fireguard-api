<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserOutput
 * @final
 *
 * DTO for User Output (Read).
 *
 * @category DTO
 * @package User\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The user ID.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $id = null;

  /**
   * Property username
   *
   * The username.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $username = null;

  /**
   * Property email
   *
   * The user email.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $email = null;

  /**
   * Property firstName
   *
   * The first name.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $firstName = null;

  /**
   * Property lastName
   *
   * The last name.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $lastName = null;

  /**
   * Property avatarUrl
   *
   * The avatar URL.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $avatarUrl = null;

  /**
   * Property status
   *
   * The user status.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $status = null;

  /**
   * Property emailVerified
   *
   * Whether the email is verified.
   *
   * @var bool
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public bool $emailVerified = false;

  /**
   * Property tenantId
   *
   * The tenant ID.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $tenantId = null;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $createdAt = null;

  /**
   * Property lastLoginAt
   *
   * The last login timestamp.
   *
   * @var string|null
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  public ?string $lastLoginAt = null;
  //#endregion
}

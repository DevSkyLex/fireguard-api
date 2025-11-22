<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Processor\RegisterUserProcessor;
use User\Presentation\Api\Provider\UserProvider;

/**
 * Resource UserResource
 * @final
 *
 * API Platform resource for User management.
 *
 * @category Resource
 * @package User\Presentation\Api\Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'User',
  operations: [
    new Post(
      uriTemplate: '/users',
      processor: RegisterUserProcessor::class,
      normalizationContext: ['groups' => ['user:read']],
      denormalizationContext: ['groups' => ['user:write']]
    ),
    new Get(
      uriTemplate: '/users/{id}',
      provider: UserProvider::class,
      normalizationContext: ['groups' => ['user:read']]
    )
  ]
)]
final class UserResource
{
  //#region Properties
  /**
   * Property id
   *
   * The user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[ApiProperty(identifier: true)]
  #[Groups(groups: ['user:read'])]
  public ?string $id = null;

  /**
   * Property username
   *
   * The username.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 50)]
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $username = null;

  /**
   * Property email
   *
   * The user email.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Email]
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $email = null;

  /**
   * Property password
   *
   * The user password (write-only).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 8)]
  #[Groups(groups: ['user:write'])]
  public ?string $password = null;

  /**
   * Property firstName
   *
   * The first name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $firstName = null;

  /**
   * Property lastName
   *
   * The last name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $lastName = null;

  /**
   * Property avatarUrl
   *
   * The avatar URL.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $avatarUrl = null;

  /**
   * Property status
   *
   * The user status.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['user:read'])]
  public ?string $status = null;

  /**
   * Property emailVerified
   *
   * Whether the email is verified.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[Groups(groups: ['user:read'])]
  public bool $emailVerified = false;

  /**
   * Property tenantId
   *
   * The tenant ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['user:read', 'user:write'])]
  public ?string $tenantId = null;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['user:read'])]
  public ?string $createdAt = null;

  /**
   * Property lastLoginAt
   *
   * The last login timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['user:read'])]
  public ?string $lastLoginAt = null;
  //#endregion
}

<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Resource;

use ApiPlatform\Metadata\{Get, GetCollection, Post, Patch, Delete, ApiResource, ApiProperty};
use Client\Presentation\Api\Processor\{
  RegisterClientProcessor,
  UpdateClientProcessor,
  RegenerateSecretProcessor,
  ActivateClientProcessor,
  DeactivateClientProcessor,
  DeleteClientProcessor
};
use Client\Presentation\Api\Provider\{GetClientProvider, ListClientsProvider};
use Shared\Domain\ValueObject\GrantType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Resource ClientResource
 * @final
 *
 * API Platform resource for Client management.
 *
 * @category Resource
 * @package Client\Presentation\Api\Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Client',
  operations: [
    new Post(
      uriTemplate: '/clients',
      processor: RegisterClientProcessor::class,
      normalizationContext: ['groups' => ['client:read', 'client:secret']],
      denormalizationContext: ['groups' => ['client:write']]
    ),
    new Get(
      uriTemplate: '/clients/{id}',
      provider: GetClientProvider::class,
      normalizationContext: ['groups' => ['client:read']]
    ),
    new GetCollection(
      uriTemplate: '/clients',
      provider: ListClientsProvider::class,
      normalizationContext: ['groups' => ['client:read']]
    ),
    new Patch(
      uriTemplate: '/clients/{id}',
      processor: UpdateClientProcessor::class,
      normalizationContext: ['groups' => ['client:read']],
      denormalizationContext: ['groups' => ['client:update']]
    ),
    new Post(
      uriTemplate: '/clients/{id}/regenerate-secret',
      processor: RegenerateSecretProcessor::class,
      normalizationContext: ['groups' => ['client:read', 'client:secret']]
    ),
    new Post(
      uriTemplate: '/clients/{id}/activate',
      processor: ActivateClientProcessor::class,
      normalizationContext: ['groups' => ['client:read']]
    ),
    new Post(
      uriTemplate: '/clients/{id}/deactivate',
      processor: DeactivateClientProcessor::class,
      normalizationContext: ['groups' => ['client:read']]
    ),
    new Delete(
      uriTemplate: '/clients/{id}',
      processor: DeleteClientProcessor::class
    )
  ]
)]
final class ClientResource
{
  //#region Properties
  /**
   * Property id
   *
   * The client ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[ApiProperty(identifier: true)]
  #[Groups(groups: ['client:read'])]
  public ?string $id = null;

  /**
   * Property name
   *
   * The client name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 100)]
  #[Groups(groups: ['client:read', 'client:write', 'client:update'])]
  public ?string $name = null;

  /**
   * Property secret
   *
   * The client secret (only visible once upon creation).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: ['client:secret'])]
  public ?string $secret = null;

  /**
   * Property redirectUris
   *
   * The allowed redirect URIs.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Url(protocols: ['http', 'https'])
  ])]
  #[Groups(groups: ['client:read', 'client:write', 'client:update'])]
  public array $redirectUris = [];

  /**
   * Property grantTypes
   *
   * The allowed grant types.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Choice(choices: GrantType::VALUES)
  ])]
  #[Groups(groups: ['client:read', 'client:write'])]
  public array $grantTypes = [];

  /**
   * Property scopes
   *
   * The allowed scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Type('string')
  ])]
  #[Groups(groups: ['client:read', 'client:write', 'client:update'])]
  public array $scopes = [];

  /**
   * Property isActive
   *
   * Whether the client is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[Groups(groups: ['client:read'])]
  public bool $isActive = true;

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
  #[Groups(groups: ['client:read'])]
  public ?string $createdAt = null;
  //#endregion
}

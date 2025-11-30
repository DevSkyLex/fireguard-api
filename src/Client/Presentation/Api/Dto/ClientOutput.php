<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Dto;

use Client\Presentation\Api\Serialization\ClientSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO ClientOutput
 * @final
 *
 * DTO for Client Output (Read).
 *
 * @category DTO
 * @package Client\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The client ID.
   *
   * @var string|null
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public ?string $id = null;

  /**
   * Property name
   *
   * The client name.
   *
   * @var string|null
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public ?string $name = null;

  /**
   * Property secret
   *
   * The client secret (only visible once upon creation/regeneration).
   *
   * @var string|null
   */
  #[Groups(groups: [ClientSerializationGroup::SECRET])]
  public ?string $secret = null;

  /**
   * Property redirectUris
   *
   * The allowed redirect URIs.
   *
   * @var list<string>
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public array $redirectUris = [];

  /**
   * Property grantTypes
   *
   * The allowed grant types.
   *
   * @var list<string>
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public array $grantTypes = [];

  /**
   * Property scopes
   *
   * The allowed scopes.
   *
   * @var list<string>
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public array $scopes = [];

  /**
   * Property isActive
   *
   * Whether the client is active.
   *
   * @var bool
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public bool $isActive = true;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @var string|null
   */
  #[Groups(groups: [ClientSerializationGroup::READ])]
  public ?string $createdAt = null;
  //#endregion
}

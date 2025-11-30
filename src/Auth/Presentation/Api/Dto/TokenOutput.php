<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO TokenOutput
 * @final
 *
 * DTO for OAuth2 Token Output.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenOutput
{
  //#region Properties
  /**
   * Property accessToken
   *
   * The access token.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $accessToken = null;

  /**
   * Property tokenType
   *
   * The token type.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $tokenType = null;

  /**
   * Property expiresIn
   *
   * The expiration time in seconds.
   *
   * @var int|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $expiresIn = null;

  /**
   * Property refreshToken
   *
   * The refresh token.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $refreshToken = null;

  /**
   * Property scope
   *
   * The scope(s) associated with the token.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;
  //#endregion
}

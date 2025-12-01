<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO LoginOutput
 * @final
 *
 * DTO for login response.
 * Note: refresh_token is sent via HttpOnly cookie, not in the response body.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginOutput
{
  /**
   * Property accessToken
   *
   * The access token (JWT).
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('access_token')]
  public ?string $accessToken = null;

  /**
   * Property tokenType
   *
   * The token type (always "Bearer").
   *
   * @var string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('token_type')]
  public string $tokenType = 'Bearer';

  /**
   * Property expiresIn
   *
   * The access token expiration time in seconds.
   *
   * @var int|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('expires_in')]
  public ?int $expiresIn = null;

  /**
   * Property scope
   *
   * The granted scopes.
   *
   * @var string|null
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;
}

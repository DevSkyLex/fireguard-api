<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO TokenOutput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenOutput
{
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName(serializedName: 'access_token')]
  public ?string $accessToken = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName(serializedName: 'token_type')]
  public ?string $tokenType = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName(serializedName: 'expires_in')]
  public ?int $expiresIn = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName(serializedName: 'refresh_token')]
  public ?string $refreshToken = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName(serializedName: 'scope')]
  public ?string $scope = null;
}

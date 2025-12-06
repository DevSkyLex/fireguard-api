<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO LoginOutput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginOutput
{
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('access_token')]
  public ?string $accessToken = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('token_type')]
  public string $tokenType = 'Bearer';

  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('expires_in')]
  public ?int $expiresIn = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;
}

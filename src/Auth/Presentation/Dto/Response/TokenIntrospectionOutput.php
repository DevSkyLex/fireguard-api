<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO TokenIntrospectionOutput
 * @final
 *
 * DTO for OAuth2 Token Introspection Output (RFC 7662).
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionOutput
{
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public bool $active = false;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $scope = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $clientId = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $username = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $tokenType = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $exp = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $iat = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $nbf = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $sub = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $aud = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $iss = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $jti = null;
}

<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO OpenIdConfigurationOutput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OpenIdConfigurationOutput
{
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $issuer = null;

  #[SerializedName('authorization_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $authorizationEndpoint = null;

  #[SerializedName('token_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $tokenEndpoint = null;

  #[SerializedName('userinfo_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $userinfoEndpoint = null;

  #[SerializedName('jwks_uri')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $jwksUri = null;

  #[SerializedName('revocation_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $revocationEndpoint = null;

  #[SerializedName('introspection_endpoint')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $introspectionEndpoint = null;

  /** @var list<string>|null */
  #[SerializedName('scopes_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $scopesSupported = null;

  /** @var list<string>|null */
  #[SerializedName('response_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $responseTypesSupported = null;

  /** @var list<string>|null */
  #[SerializedName('grant_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $grantTypesSupported = null;

  /** @var list<string>|null */
  #[SerializedName('token_endpoint_auth_methods_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $tokenEndpointAuthMethodsSupported = null;

  /** @var list<string>|null */
  #[SerializedName('code_challenge_methods_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $codeChallengeMethodsSupported = null;

  /** @var list<string>|null */
  #[SerializedName('subject_types_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $subjectTypesSupported = null;

  /** @var list<string>|null */
  #[SerializedName('id_token_signing_alg_values_supported')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?array $idTokenSigningAlgValuesSupported = null;
}

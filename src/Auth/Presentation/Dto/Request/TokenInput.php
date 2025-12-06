<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Request;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Auth\Presentation\Validation\GrantTypeRequirements\GrantTypeRequirements;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenInput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Request
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[GrantTypeRequirements]
final class TokenInput
{
  #[Assert\NotBlank(message: 'The grant_type field is required.')]
  #[Assert\Choice(
    choices: ['client_credentials', 'refresh_token', 'authorization_code'],
    message: 'Invalid grant_type. Allowed values: client_credentials, refresh_token, authorization_code.'
  )]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'grant_type')]
  public ?string $grantType = null;

  #[Assert\NotBlank]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'client_id')]
  public ?string $clientId = null;

  #[Assert\NotBlank]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'client_secret')]
  public ?string $clientSecret = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'scope')]
  public ?string $scope = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'refresh_token')]
  public ?string $refreshToken = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'code')]
  public ?string $code = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'redirect_uri')]
  public ?string $redirectUri = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName(serializedName: 'code_verifier')]
  public ?string $codeVerifier = null;
}

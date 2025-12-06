<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Request;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenIntrospectionInput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Request
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionInput
{
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $token = null;

  #[Assert\Choice(
    choices: ['access_token', 'refresh_token'],
    message: 'Invalid token_type_hint. Allowed values: access_token, refresh_token.'
  )]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $tokenTypeHint = null;
}

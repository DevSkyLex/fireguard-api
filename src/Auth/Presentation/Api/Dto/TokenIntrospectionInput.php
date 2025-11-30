<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenIntrospectionInput
 * @final
 *
 * DTO for OAuth2 Token Introspection Input (RFC 7662).
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7662
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionInput
{
  //#region Properties
  /**
   * Property token
   *
   * The token to introspect.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $token = null;

  /**
   * Property tokenTypeHint
   *
   * A hint about the type of the token submitted for introspection.
   * Valid values: access_token, refresh_token.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Assert\Choice(
    choices: ['access_token', 'refresh_token'],
    message: 'Invalid token_type_hint. Allowed values: access_token, refresh_token.'
  )]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $tokenTypeHint = null;
  //#endregion
}

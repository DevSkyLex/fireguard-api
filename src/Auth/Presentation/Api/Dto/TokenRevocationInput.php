<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenRevocationInput
 * @final
 *
 * DTO for OAuth2 Token Revocation Input.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenRevocationInput
{
  //#region Properties
  /**
   * Property token
   *
   * The token to revoke.
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  public ?string $token = null;

  /**
   * Property tokenTypeHint
   *
   * The hint about the type of the token submitted for revocation.
   *
   * @var string|null
   */
  #[Assert\Choice(choices: ['access_token', 'refresh_token'])]
  public ?string $tokenTypeHint = null;
  //#endregion
}

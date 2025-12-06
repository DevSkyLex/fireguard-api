<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenRevocationInput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Request
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenRevocationInput
{
  #[Assert\NotBlank]
  public ?string $token = null;

  #[Assert\Choice(choices: ['access_token', 'refresh_token'])]
  public ?string $tokenTypeHint = null;
}

<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Password;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO PasswordPolicyOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordPolicyOutput
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $minimumLength minimum password length
   * @param int $maximumLength maximum password length
   * @param bool $requireUppercase uppercase requirement
   * @param bool $requireLowercase lowercase requirement
   * @param bool $requireDigit digit requirement
   * @param string $specialCharacters required special characters
   * @param string|null $allowedInitialCharacters permitted first-character alphabet, or null when unrestricted
   */
  public function __construct(
    #[Groups(['password_policy:read'])]
    public int $minimumLength,
    #[Groups(['password_policy:read'])]
    public int $maximumLength,
    #[Groups(['password_policy:read'])]
    public bool $requireUppercase,
    #[Groups(['password_policy:read'])]
    public bool $requireLowercase,
    #[Groups(['password_policy:read'])]
    public bool $requireDigit,
    #[Groups(['password_policy:read'])]
    public string $specialCharacters,
    #[Groups(['password_policy:read'])]
    public ?string $allowedInitialCharacters = null,
  ) {
  }
}

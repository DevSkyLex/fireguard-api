<?php

declare(strict_types=1);

namespace User\Application\Contract\Password;

/**
 * Contract PasswordPolicyDescription.
 *
 * Contains only public requirements, never credentials or account state.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordPolicyDescription
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $minimumLength minimum character count
   * @param int $maximumLength maximum character count
   * @param bool $requireUppercase whether an uppercase letter is required
   * @param bool $requireLowercase whether a lowercase letter is required
   * @param bool $requireDigit whether a digit is required
   * @param string $specialCharacters required special-character alphabet
   * @param string|null $allowedInitialCharacters permitted first-character alphabet, or null when unrestricted
   */
  public function __construct(
    public int $minimumLength,
    public int $maximumLength,
    public bool $requireUppercase,
    public bool $requireLowercase,
    public bool $requireDigit,
    public string $specialCharacters,
    public ?string $allowedInitialCharacters = null,
  ) {
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use SensitiveParameter;
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function preg_match;
use function preg_quote;

/**
 * ValueObject PasswordPolicy.
 *
 * Defines project-wide password requirements without depending on HTTP validation.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $minimumLength minimum password character count
   * @param int $maximumLength maximum password character count
   * @param bool $requireUppercase whether an uppercase letter is required
   * @param bool $requireLowercase whether a lowercase letter is required
   * @param bool $requireDigit whether a digit is required
   * @param string $specialCharacters required special-character alphabet, or empty to disable that requirement
   */
  public function __construct(
    public int $minimumLength = 8,
    public int $maximumLength = 128,
    public bool $requireUppercase = true,
    public bool $requireLowercase = true,
    public bool $requireDigit = true,
    public string $specialCharacters = '@$!%*?&#',
  ) {
    if ($minimumLength < 1 || $maximumLength < $minimumLength || $maximumLength > 4096) {
      throw InvalidValueException::because('Invalid password policy length bounds.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method violations.
   *
   * @since 1.0.0
   *
   * @param string $password the proposed plaintext, never retained
   *
   * @return list<string> stable identifiers of violated requirements
   */
  public function violations(#[SensitiveParameter] string $password): array
  {
    $violations = [];
    $length = mb_strlen($password);
    if ($length < $this->minimumLength) {
      $violations[] = 'minimum_length';
    }
    if ($length > $this->maximumLength) {
      $violations[] = 'maximum_length';
    }
    $specialPattern = '/[' . preg_quote($this->specialCharacters, '/') . ']/';
    if (1 === preg_match('/[^\x00-\x7F]/', $this->specialCharacters)) {
      $specialPattern .= 'u';
    }
    $complexity = ($this->requireUppercase && 1 !== preg_match('/[A-Z]/', $password))
      || ($this->requireLowercase && 1 !== preg_match('/[a-z]/', $password))
      || ($this->requireDigit && 1 !== preg_match('/[0-9]/', $password))
      || ('' !== $this->specialCharacters && 1 !== preg_match($specialPattern, $password));

    // The historical HTTP rule also requires its first character to belong to
    // this alphabet; retain that exact default behavior during centralization.
    if (null !== $this->allowedInitialCharacters()) {
      $complexity = $complexity || 1 !== preg_match('/^[A-Za-z0-9@$!%*?&#]/', $password);
    }
    if ($complexity) {
      $violations[] = 'complexity';
    }

    return $violations;
  }

  /**
   * Method assertAcceptable.
   *
   * @since 1.0.0
   *
   * @param string $password the proposed password
   *
   * @return void
   */
  public function assertAcceptable(#[SensitiveParameter] string $password): void
  {
    $violations = $this->violations($password);
    if ([] !== $violations) {
      throw InvalidValueException::because('The password does not satisfy the configured password policy.');
    }
  }

  /**
   * Method allowedInitialCharacters
   *
   * Publishes the historical initial-character rule only when its legacy complexity configuration is installed.
   *
   * @access public
   *
   * @return string|null permitted initial alphabet, or null when no initial-character rule applies
   */
  public function allowedInitialCharacters(): ?string
  {
    return '@$!%*?&#' === $this->specialCharacters && $this->requireUppercase && $this->requireLowercase && $this->requireDigit
      ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$!%*?&#'
      : null;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject OtpCode
 * @final
 *
 * Represents a one-time password code.
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpCode
{
  //#region Constants
  /**
   * Constant CODE_LENGTH
   *
   * Default length of OTP code.
   *
   * @access private
   * @since 1.0.0
   * 
   * @var int
   */
  private const int CODE_LENGTH = 6;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of 
   * the OtpCode class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $plainCode The plain text code.
   * @param string $hashedCode The hashed code for storage.
   */
  private function __construct(
    private readonly string $plainCode,
    private readonly string $hashedCode,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method generate
   * @static
   *
   * Generates a new random OTP code.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $length The code length.
   *
   * @return self The generated OTP code.
   */
  public static function generate(int $length = self::CODE_LENGTH): self
  {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
      $code .= random_int(0, 9);
    }

    return new self(
      plainCode: $code,
      hashedCode: password_hash(
        password: $code,
        algo: PASSWORD_ARGON2ID,
      ),
    );
  }

  /**
   * Method fromHash
   * @static
   *
   * Creates an OtpCode from a stored hash (for verification).
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $hashedCode The hashed code.
   *
   * @return self The OTP code instance.
   */
  public static function fromHash(string $hashedCode): self
  {
    return new self(
      plainCode: '',
      hashedCode: $hashedCode,
    );
  }

  /**
   * Method verify
   *
   * Verifies a plain code against the hash.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $inputCode The input code to verify.
   *
   * @return bool True if the code matches.
   */
  public function verify(string $inputCode): bool
  {
    return password_verify(
      password: $inputCode, 
      hash: $this->hashedCode
    );
  }

  /**
   * Method plain
   *
   * Returns the plain code (only available immediately after generation).
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The plain code.
   *
   * @throws InvalidValueException If plain code is not available.
   */
  public function plain(): string
  {
    if ($this->plainCode === '') {
      throw InvalidValueException::because(
        message: 'Plain code is only available immediately after generation.'
      );
    }

    return $this->plainCode;
  }

  /**
   * Method hash
   *
   * Returns the hashed code for storage.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The hashed code.
   */
  public function hash(): string
  {
    return $this->hashedCode;
  }

  /**
   * Method masked
   *
   * Returns a masked version of 
   * the code for display.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The masked code (e.g., "**3456").
   */
  public function masked(): string
  {
    if ($this->plainCode === '') {
      return '******';
    }

    $visible = substr($this->plainCode, -2);
    return str_repeat('*', strlen($this->plainCode) - 2) . $visible;
  }
  //#endregion
}

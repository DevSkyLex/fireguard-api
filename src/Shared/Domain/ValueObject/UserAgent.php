<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject UserAgent
 * @final
 *
 * Represents a User-Agent string from HTTP requests.
 * Useful for device detection, security, audit logs.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserAgent implements Stringable
{
  //#region Constants
  /**
   * Constant MAX_LENGTH
   *
   * Maximum allowed length for a User-Agent string.
   *
   * @access private
   * @since 1.0.0
   *
   * @var int MAX_LENGTH
   */
  private const int MAX_LENGTH = 512;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UserAgent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The User-Agent string.
   *
   * @throws InvalidValueException If the User-Agent is invalid.
   */
  public function __construct(public string $value)
  {
    if (empty($value)) {
      throw InvalidValueException::because(
        message: 'User-Agent cannot be empty.'
      );
    }

    if (strlen(string: $value) > self::MAX_LENGTH) {
      throw InvalidValueException::because(
        message: sprintf('User-Agent cannot exceed %d characters.', self::MAX_LENGTH)
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method isMobile
   *
   * Checks if the User-Agent indicates a mobile device.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if mobile, false otherwise.
   */
  public function isMobile(): bool
  {
    return preg_match(
      pattern: '/(Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini)/i',
      subject: $this->value
    ) === 1;
  }

  /**
   * Method isBot
   *
   * Checks if the User-Agent indicates a bot/crawler.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if bot, false otherwise.
   */
  public function isBot(): bool
  {
    return preg_match(
      pattern: '/(bot|crawler|spider|scraper|slurp|wget|curl)/i',
      subject: $this->value
    ) === 1;
  }

  /**
   * Method getBrowser
   *
   * Attempts to extract the browser name from the User-Agent.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The browser name or null if not detected.
   */
  public function getBrowser(): ?string
  {
    $browsers = [
      'Chrome' => '/Chrome\/[\d.]+/i',
      'Firefox' => '/Firefox\/[\d.]+/i',
      'Safari' => '/Safari\/[\d.]+/i',
      'Edge' => '/Edg\/[\d.]+/i',
      'Opera' => '/Opera\/[\d.]+/i',
      'IE' => '/MSIE [\d.]+/i',
    ];

    foreach ($browsers as $name => $pattern) {
      if (preg_match(pattern: $pattern, subject: $this->value)) {
        return $name;
      }
    }

    return null;
  }

  /**
   * Method getOS
   *
   * Attempts to extract the operating system from the User-Agent.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The OS name or null if not detected.
   */
  public function getOS(): ?string
  {
    $operatingSystems = [
      'Windows' => '/Windows NT [\d.]+/i',
      'macOS' => '/Mac OS X [\d_]+/i',
      'Linux' => '/Linux/i',
      'Android' => '/Android [\d.]+/i',
      'iOS' => '/(iPhone|iPad|iPod).*OS [\d_]+/i',
    ];

    foreach ($operatingSystems as $name => $pattern) {
      if (preg_match(pattern: $pattern, subject: $this->value)) {
        return $name;
      }
    }

    return null;
  }

  /**
   * Method equals
   *
   * Compares two UserAgent objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other UserAgent object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the UserAgent object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the UserAgent object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}

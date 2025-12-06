<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;

/**
 * Class TokenExpiry
 * @final
 *
 * Value object representing a token expiration time.
 *
 * @category ValueObject
 * @package Auth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenExpiry
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenExpiry class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt The expiration date.
   */
  public function __construct(
    public DateTimeImmutable $expiresAt
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method fromSeconds
   *
   * Creates an expiry from seconds from now.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds The number of seconds.
   *
   * @return self The token expiry.
   */
  public static function fromSeconds(int $seconds): self
  {
    $now = new DateTimeImmutable();
    return new self($now->add(new DateInterval("PT{$seconds}S")));
  }

  /**
   * Method fromMinutes
   *
   * Creates an expiry from minutes from now.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $minutes The number of minutes.
   *
   * @return self The token expiry.
   */
  public static function fromMinutes(int $minutes): self
  {
    return self::fromSeconds($minutes * 60);
  }

  /**
   * Method fromHours
   *
   * Creates an expiry from hours from now.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $hours The number of hours.
   *
   * @return self The token expiry.
   */
  public static function fromHours(int $hours): self
  {
    return self::fromSeconds($hours * 3600);
  }

  /**
   * Method fromDays
   *
   * Creates an expiry from days from now.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $days The number of days.
   *
   * @return self The token expiry.
   */
  public static function fromDays(int $days): self
  {
    return self::fromSeconds($days * 86400);
  }

  /**
   * Method isExpired
   *
   * Checks if the token is expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired.
   */
  public function isExpired(): bool
  {
    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method secondsRemaining
   *
   * Returns the seconds remaining until expiry.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The seconds remaining (0 if expired).
   */
  public function secondsRemaining(): int
  {
    $now = new DateTimeImmutable();
    $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();
    return max(0, $diff);
  }

  /**
   * Method timestamp
   *
   * Returns the Unix timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The Unix timestamp.
   */
  public function timestamp(): int
  {
    return $this->expiresAt->getTimestamp();
  }
  //#endregion
}

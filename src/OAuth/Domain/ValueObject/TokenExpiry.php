<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject TokenExpiry
 * @final
 *
 * Represents a token expiry time with PHP 8.4 property hooks.
 * Demonstrates the new property hooks feature for computed properties.
 *
 * @category ValueObject
 * @package OAuth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenExpiry
{
  //#region Properties
  /**
   * Property expiresAt
   *
   * The expiry timestamp (immutable after construction).
   *
   * @var DateTimeImmutable
   */
  public private(set) DateTimeImmutable $expiresAt;

  /**
   * Property isExpired
   *
   * Whether the token is expired (computed via property hook).
   *
   * @var bool
   */
  public bool $isExpired {
    get => $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Property remainingSeconds
   *
   * Remaining seconds until expiry (computed via property hook).
   *
   * @var int
   */
  public int $remainingSeconds {
    get {
      $remaining = $this->expiresAt->getTimestamp() - time();
      return max(0, $remaining);
    }
  }

  /**
   * Property expiresInMinutes
   *
   * Remaining minutes until expiry (computed via property hook).
   *
   * @var int
   */
  public int $expiresInMinutes {
    get => (int) ceil($this->remainingSeconds / 60);
  }
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt The expiry timestamp.
   */
  public function __construct(DateTimeImmutable $expiresAt)
  {
    $this->expiresAt = $expiresAt;
  }
  //#endregion

  //#region Methods
  /**
   * Method fromTtl
   * @static
   *
   * Creates a TokenExpiry from a TTL in seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $ttlSeconds The TTL in seconds.
   *
   * @return self The TokenExpiry instance.
   */
  public static function fromTtl(int $ttlSeconds): self
  {
    if ($ttlSeconds <= 0) {
      throw InvalidValueException::because('TTL must be positive.');
    }

    return new self(
      new DateTimeImmutable(sprintf('+%d seconds', $ttlSeconds))
    );
  }

  /**
   * Method fromSeconds
   * @static
   *
   * Alias for fromTtl.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds The seconds.
   *
   * @return self The TokenExpiry instance.
   */
  public static function fromSeconds(int $seconds): self
  {
    return self::fromTtl($seconds);
  }

  /**
   * Method fromTimestamp
   * @static
   *
   * Creates a TokenExpiry from a Unix timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $timestamp The Unix timestamp.
   *
   * @return self The TokenExpiry instance.
   */
  public static function fromTimestamp(int $timestamp): self
  {
    return new self(
      (new DateTimeImmutable())->setTimestamp($timestamp)
    );
  }

  /**
   * Method willExpireWithin
   *
   * Checks if the token will expire within the given seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds The number of seconds.
   *
   * @return bool True if will expire within the time, false otherwise.
   */
  public function willExpireWithin(int $seconds): bool
  {
    return $this->remainingSeconds <= $seconds;
  }

  /**
   * Method extend
   *
   * Returns a new TokenExpiry with extended time.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds The number of seconds to extend.
   *
   * @return self The new TokenExpiry instance.
   */
  public function extend(int $seconds): self
  {
    return new self(
      $this->expiresAt->modify(sprintf('+%d seconds', $seconds))
    );
  }

  /**
   * Method isExpired
   *
   * Compatibility method for code that calls isExpired().
   * Uses the property hook internally.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired, false otherwise.
   */
  public function isExpired(): bool
  {
    return $this->isExpired;
  }

  /**
   * Method secondsRemaining
   *
   * Compatibility method for code that calls secondsRemaining().
   * Uses the property hook internally.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The remaining seconds.
   */
  public function secondsRemaining(): int
  {
    return $this->remainingSeconds;
  }
  //#endregion
}

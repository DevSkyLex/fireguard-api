<?php

declare(strict_types=1);

namespace Shared\Domain\Trait;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function ceil;
use function max;
use function sprintf;
use function time;

/**
 * Trait HasTokenExpiry
 *
 * Shares absolute expiry hooks and factories while binding self to each consuming domain type.
 *
 * @category Trait
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait HasTokenExpiry
{
  // #region Properties
  /**
   * Property expiresAt.
   *
   * The expiry timestamp (immutable after construction).
   */
  public readonly DateTimeImmutable $expiresAt;

  /**
   * Property isExpired.
   *
   * Whether the token is expired (computed via property hook).
   */
  public bool $isExpired {
    get {
      return $this->expiresAt < new DateTimeImmutable();
    }
  }

  /**
   * Property remainingSeconds.
   *
   * Remaining seconds until expiry (computed via property hook).
   */
  public int $remainingSeconds {
    get {
      $remaining = $this->expiresAt->getTimestamp() - time();

      return max(0, $remaining);
    }
  }

  /**
   * Property expiresInMinutes.
   *
   * Remaining minutes until expiry (computed via property hook).
   */
  public int $expiresInMinutes {
    get {
      return (int) ceil($this->remainingSeconds / 60);
    }
  }
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt the expiry timestamp
   */
  public function __construct(DateTimeImmutable $expiresAt)
  {
    $this->expiresAt = $expiresAt;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromTtl.
   *
   * @static
   *
   * Creates the consuming expiry type from a positive TTL in seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $ttlSeconds the TTL in seconds
   *
   * @return self the consuming expiry instance
   */
  public static function fromTtl(int $ttlSeconds): self
  {
    if ($ttlSeconds <= 0) {
      throw InvalidValueException::because('TTL must be positive.');
    }

    return new self(
      new DateTimeImmutable(sprintf('+%d seconds', $ttlSeconds)),
    );
  }

  /**
   * Method fromSeconds.
   *
   * @static
   *
   * Alias for fromTtl.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds the seconds
   *
   * @return self the consuming expiry instance
   */
  public static function fromSeconds(int $seconds): self
  {
    return self::fromTtl($seconds);
  }

  /**
   * Method fromTimestamp.
   *
   * @static
   *
   * Creates the consuming expiry type at the supplied absolute Unix timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $timestamp the Unix timestamp
   *
   * @return self the consuming expiry instance
   */
  public static function fromTimestamp(int $timestamp): self
  {
    return new self(
      new DateTimeImmutable()->setTimestamp($timestamp),
    );
  }

  /**
   * Method willExpireWithin.
   *
   * Checks if the token will expire within the given seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds the number of seconds
   *
   * @return bool true if will expire within the time, false otherwise
   */
  public function willExpireWithin(int $seconds): bool
  {
    return $this->remainingSeconds <= $seconds;
  }

  /**
   * Method extend.
   *
   * Returns a new consuming expiry value relative to the original deadline.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $seconds the number of seconds to extend
   *
   * @return self the new consuming expiry instance
   */
  public function extend(int $seconds): self
  {
    return new self(
      $this->expiresAt->modify(sprintf('+%d seconds', $seconds)),
    );
  }

  /**
   * Method isExpired.
   *
   * Compatibility method for code that calls isExpired().
   * Uses the property hook internally.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true if expired, false otherwise
   */
  public function isExpired(): bool
  {
    return $this->isExpired;
  }

  /**
   * Method secondsRemaining.
   *
   * Compatibility method for code that calls secondsRemaining().
   * Uses the property hook internally.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int the remaining seconds
   */
  public function secondsRemaining(): int
  {
    return $this->remainingSeconds;
  }
  // #endregion
}

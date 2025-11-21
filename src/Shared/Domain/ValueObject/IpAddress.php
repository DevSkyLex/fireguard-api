<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject IpAddress
 * @final
 *
 * Represents an IP address (IPv4 or IPv6).
 * Useful for security, audit logs, rate limiting, etc.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class IpAddress implements Stringable
{
  //#region Properties
  /**
   * Property type
   *
   * The type of IP address (IPv4 or IPv6).
   *
   * @access public
   * @since 2.0.0
   *
   * @var IpAddressType $type
   */
  public IpAddressType $type;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the IpAddress class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The IP address.
   *
   * @throws InvalidValueException If the IP address is invalid.
   */
  public function __construct(public string $value)
  {
    if (!filter_var(value: $value, filter: FILTER_VALIDATE_IP)) {
      throw InvalidValueException::because(
        message: 'Invalid IP address.'
      );
    }

    $this->type = filter_var(value: $value, filter: FILTER_VALIDATE_IP, options: FILTER_FLAG_IPV6)
      ? IpAddressType::IPV6
      : IpAddressType::IPV4;
  }
  //#endregion

  //#region Methods
  /**
   * Method isIpv4
   *
   * Checks if the IP address is IPv4.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if IPv4, false otherwise.
   */
  public function isIpv4(): bool
  {
    return $this->type === IpAddressType::IPV4;
  }

  /**
   * Method isIpv6
   *
   * Checks if the IP address is IPv6.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if IPv6, false otherwise.
   */
  public function isIpv6(): bool
  {
    return $this->type === IpAddressType::IPV6;
  }

  /**
   * Method isPrivate
   *
   * Checks if the IP address is in 
   * a private range.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if private, false otherwise.
   */
  public function isPrivate(): bool
  {
    return !filter_var(
      value: $this->value,
      filter: FILTER_VALIDATE_IP,
      options: FILTER_FLAG_NO_PRIV_RANGE
    );
  }

  /**
   * Method isReserved
   *
   * Checks if the IP address is in 
   * a reserved range.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if reserved, false otherwise.
   */
  public function isReserved(): bool
  {
    return !filter_var(
      value: $this->value,
      filter: FILTER_VALIDATE_IP,
      options: FILTER_FLAG_NO_RES_RANGE
    );
  }

  /**
   * Method isLoopback
   *
   * Checks if the IP address is a 
   * loopback address.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if loopback, false otherwise.
   */
  public function isLoopback(): bool
  {
    return $this->value === '127.0.0.1'
      || $this->value === '::1'
      || str_starts_with(haystack: $this->value, needle: '127.');
  }

  /**
   * Method equals
   *
   * Compares two IpAddress objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other IpAddress object to compare.
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
   * Returns the string representation of the IpAddress object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the IpAddress object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}

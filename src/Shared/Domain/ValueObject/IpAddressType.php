<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

/**
 * Enum IpAddressType
 *
 * Represents the type of an IP address (IPv4 or IPv6).
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum IpAddressType: string
{
  /**
   * IPv4 address type.
   * Example: 192.168.1.1
   */
  case IPV4 = 'ipv4';

  /**
   * IPv6 address type.
   * Example: 2001:0db8:85a3:0000:0000:8a2e:0370:7334
   */
  case IPV6 = 'ipv6';

  /**
   * Method label
   *
   * Returns a human-readable label for the IP address type.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The human-readable label.
   */
  public function label(): string
  {
    return match ($this) {
      self::IPV4 => 'IPv4',
      self::IPV6 => 'IPv6',
    };
  }
}

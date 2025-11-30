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
  //#region Constants
  /**
   * Case IPV4
   * 
   * IPv4 address type.
   * 
   * @since 1.0.0
   */
  case IPV4 = 'ipv4';

  /**
   * Case IPV6
   * 
   * IPv6 address type.
   * 
   * @since 1.0.0
   */
  case IPV6 = 'ipv6';
  //#endregion

  //#region Methods
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
  //#endregion
}

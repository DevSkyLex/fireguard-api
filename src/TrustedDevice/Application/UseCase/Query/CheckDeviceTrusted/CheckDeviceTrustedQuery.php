<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Query CheckDeviceTrustedQuery
 * @final
 *
 * Query to check if a device is trusted.
 *
 * @category Query
 * @package TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedQuery
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * CheckDeviceTrustedQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The token.
   * @param string $userId The user ID.
   */
  public function __construct(
    public readonly string $token,
    public readonly string $userId,
  ) {}
  //#endregion
}

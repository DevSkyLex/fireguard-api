<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Result CheckDeviceTrustedResult
 * @final
 *
 * Result of device trust check.
 *
 * @category Result
 * @package TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * CheckDeviceTrustedResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $trusted The trusted status.
   * @param ?string $deviceId The device ID.
   * @param ?string $deviceName The device name.
   */
  public function __construct(
    public readonly bool $trusted,
    public readonly ?string $deviceId = null,
    public readonly ?string $deviceName = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method trusted
   *
   * Creates a trusted device result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $deviceId The device ID.
   * @param string $deviceName The device name.
   *
   * @return CheckDeviceTrustedResult The result.
   */
  public static function trusted(string $deviceId, string $deviceName): self
  {
    return new self(
      trusted: true,
      deviceId: $deviceId,
      deviceName: $deviceName,
    );
  }

  /**
   * Method notTrusted
   *
   * Creates a not trusted device result.
   *
   * @access public
   * @since 1.0.0
   *
   * @return CheckDeviceTrustedResult The result.
   */
  public static function notTrusted(): self
  {
    return new self(trusted: false);
  }
  //#endregion
}

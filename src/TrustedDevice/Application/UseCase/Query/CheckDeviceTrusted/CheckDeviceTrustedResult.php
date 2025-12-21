<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Result CheckDeviceTrustedResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CheckDeviceTrustedResult class.
   *
   * @since 1.0.0
   *
   * @param bool $trusted the trusted status
   * @param ?string $deviceId the device ID
   * @param ?string $deviceName the device name
   */
  public function __construct(
    public readonly bool $trusted,
    public readonly ?string $deviceId = null,
    public readonly ?string $deviceName = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method trusted.
   *
   * Creates a trusted device result.
   *
   * @since 1.0.0
   *
   * @param string $deviceId the device ID
   * @param string $deviceName the device name
   *
   * @return CheckDeviceTrustedResult the result
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
   * Method notTrusted.
   *
   * Creates a not trusted device result.
   *
   * @since 1.0.0
   *
   * @return CheckDeviceTrustedResult the result
   */
  public static function notTrusted(): self
  {
    return new self(trusted: false);
  }
  // #endregion
}

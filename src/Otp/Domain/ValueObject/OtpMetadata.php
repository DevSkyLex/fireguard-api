<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/**
 * ValueObject OtpMetadata
 * @final
 *
 * Holds metadata about the OTP request context (IP, user agent, device).
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpMetadata
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of 
   * the OtpMetadata class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $ipAddress The client IP address.
   * @param string|null $userAgent The client user agent.
   * @param string|null $deviceId The device identifier.
   */
  public function __construct(
    public ?string $ipAddress = null,
    public ?string $userAgent = null,
    public ?string $deviceId = null,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new OtpMetadata instance.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $ipAddress The client IP address.
   * @param string|null $userAgent The client user agent.
   * @param string|null $deviceId The device identifier.
   *
   * @return self
   */
  public static function create(
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?string $deviceId = null,
  ): self {
    return new self(
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      deviceId: $deviceId,
    );
  }

  /**
   * Method fromArray
   * @static
   *
   * Creates from an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $data The data array.
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    return new self(
      ipAddress: $data['ip_address'] ?? $data['ipAddress'] ?? null,
      userAgent: $data['user_agent'] ?? $data['userAgent'] ?? null,
      deviceId: $data['device_id'] ?? $data['deviceId'] ?? null,
    );
  }

  /**
   * Method toArray
   *
   * Converts to an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, string|null>
   */
  public function toArray(): array
  {
    return [
      'ip_address' => $this->ipAddress,
      'user_agent' => $this->userAgent,
      'device_id' => $this->deviceId,
    ];
  }

  /**
   * Method isEmpty
   *
   * Checks if all fields are null.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool
   */
  public function isEmpty(): bool
  {
    return $this->ipAddress === null
      && $this->userAgent === null
      && $this->deviceId === null;
  }
  //#endregion
}

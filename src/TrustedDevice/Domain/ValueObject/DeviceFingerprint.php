<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\ValueObject;

/**
 * ValueObject DeviceFingerprint
 * @final
 *
 * Represents a device fingerprint for identification.
 *
 * @category ValueObject
 * @package TrustedDevice\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeviceFingerprint
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param string $value The fingerprint hash.
   * @param string $userAgent The user agent string.
   * @param string|null $ipAddress The IP address (optional).
   */
  public function __construct(
    public string $value,
    public string $userAgent,
    public ?string $ipAddress = null,
  ) {
  }
  //#endregion

  //#region Factory Methods
  /**
   * Method create
   * @static
   *
   * Creates a fingerprint from request data.
   *
   * @param string $userAgent The user agent.
   * @param string|null $ipAddress The IP address (optional).
   * @param string|null $acceptLanguage The accept-language header.
   *
   * @return self
   */
  public static function create(
    string $userAgent,
    ?string $ipAddress = null,
    ?string $acceptLanguage = null,
  ): self {
    // Create hash from components
    $components = [
      'ua' => $userAgent,
      'lang' => $acceptLanguage ?? '',
    ];

    // Include IP if provided
    if ($ipAddress !== null) {
      $components['ip'] = $ipAddress;
    }

    $hash = hash('sha256', json_encode($components, JSON_THROW_ON_ERROR));

    return new self(
      value: $hash,
      userAgent: $userAgent,
      ipAddress: $ipAddress,
    );
  }

  /**
   * Method fromHash
   * @static
   *
   * Creates a fingerprint from stored hash.
   *
   * @param string $hash The stored hash.
   * @param string $userAgent The user agent.
   * @param string|null $ipAddress The IP address.
   *
   * @return self
   */
  public static function fromHash(
    string $hash,
    string $userAgent,
    ?string $ipAddress = null,
  ): self {
    return new self(
      value: $hash,
      userAgent: $userAgent,
      ipAddress: $ipAddress,
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method matches
   *
   * Checks if this fingerprint matches another.
   *
   * @param self $other The other fingerprint.
   *
   * @return bool True if matching.
   */
  public function matches(self $other): bool
  {
    return hash_equals($this->value, $other->value);
  }

  /**
   * Method getDeviceName
   *
   * Returns a human-readable device name.
   *
   * @return string The device name.
   */
  public function getDeviceName(): string
  {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';

    // Detect browser
    if (str_contains($this->userAgent, 'Chrome')) {
      $browser = 'Chrome';
    } elseif (str_contains($this->userAgent, 'Firefox')) {
      $browser = 'Firefox';
    } elseif (str_contains($this->userAgent, 'Safari')) {
      $browser = 'Safari';
    } elseif (str_contains($this->userAgent, 'Edge')) {
      $browser = 'Edge';
    }

    // Detect OS
    if (str_contains($this->userAgent, 'Windows')) {
      $os = 'Windows';
    } elseif (str_contains($this->userAgent, 'Mac')) {
      $os = 'macOS';
    } elseif (str_contains($this->userAgent, 'Linux')) {
      $os = 'Linux';
    } elseif (str_contains($this->userAgent, 'Android')) {
      $os = 'Android';
    } elseif (str_contains($this->userAgent, 'iPhone') || str_contains($this->userAgent, 'iPad')) {
      $os = 'iOS';
    }

    return "{$browser} on {$os}";
  }
  //#endregion
}

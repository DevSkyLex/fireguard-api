<?php

declare(strict_types=1);

namespace Session\Domain\ValueObject;

/**
 * ValueObject SessionMetadata
 * @final
 *
 * Represents additional session metadata.
 *
 * @category ValueObject
 * @package Session\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionMetadata
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $deviceType The device type (mobile, desktop, tablet).
   * @param string|null $browser The browser name.
   * @param string|null $operatingSystem The operating system.
   * @param string|null $country The country code.
   * @param string|null $city The city name.
   * @param bool $rememberMe Whether the session is persistent.
   */
  public function __construct(
    public ?string $deviceType = null,
    public ?string $browser = null,
    public ?string $operatingSystem = null,
    public ?string $country = null,
    public ?string $city = null,
    public bool $rememberMe = false,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method toArray
   *
   * Returns the metadata as an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The metadata array.
   */
  public function toArray(): array
  {
    return [
      'device_type' => $this->deviceType,
      'browser' => $this->browser,
      'operating_system' => $this->operatingSystem,
      'country' => $this->country,
      'city' => $this->city,
      'remember_me' => $this->rememberMe,
    ];
  }

  /**
   * Method fromArray
   * @static
   *
   * Creates metadata from an array.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $data The metadata data.
   *
   * @return self The metadata instance.
   */
  public static function fromArray(array $data): self
  {
    $deviceType = $data['device_type'] ?? null;
    $browser = $data['browser'] ?? null;
    $operatingSystem = $data['operating_system'] ?? null;
    $country = $data['country'] ?? null;
    $city = $data['city'] ?? null;
    $rememberMe = $data['remember_me'] ?? false;

    return new self(
      deviceType: is_scalar($deviceType) ? (string) $deviceType : null,
      browser: is_scalar($browser) ? (string) $browser : null,
      operatingSystem: is_scalar($operatingSystem) ? (string) $operatingSystem : null,
      country: is_scalar($country) ? (string) $country : null,
      city: is_scalar($city) ? (string) $city : null,
      rememberMe: (bool) $rememberMe,
    );
  }
  //#endregion
}

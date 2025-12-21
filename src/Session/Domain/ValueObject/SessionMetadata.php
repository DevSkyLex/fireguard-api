<?php

declare(strict_types=1);

namespace Session\Domain\ValueObject;

use function is_scalar;

/**
 * ValueObject SessionMetadata.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionMetadata
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string|null $deviceType the device type (mobile, desktop, tablet)
   * @param string|null $browser the browser name
   * @param string|null $operatingSystem the operating system
   * @param string|null $country the country code
   * @param string|null $city the city name
   * @param bool $rememberMe whether the session is persistent
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
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Returns the metadata as an array.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the metadata array
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
   * Method fromArray.
   *
   * @static
   *
   * Creates metadata from an array.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the metadata data
   *
   * @return self the metadata instance
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
  // #endregion
}

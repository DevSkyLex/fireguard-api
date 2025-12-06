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
  ) {}
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
    return new self(
      deviceType: isset($data['device_type']) ? (string) $data['device_type'] : null,
      browser: isset($data['browser']) ? (string) $data['browser'] : null,
      operatingSystem: isset($data['operating_system']) ? (string) $data['operating_system'] : null,
      country: isset($data['country']) ? (string) $data['country'] : null,
      city: isset($data['city']) ? (string) $data['city'] : null,
      rememberMe: (bool) ($data['remember_me'] ?? false),
    );
  }
  //#endregion
}

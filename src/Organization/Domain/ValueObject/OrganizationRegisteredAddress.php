<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function trim;

/**
 * Class OrganizationRegisteredAddress
 *
 * Stores a partial registered office independently of any facility address.
 * Empty components are normalized to null; country codes use the organization ISO rule.
 *
 * @category ValueObject
 */
final readonly class OrganizationRegisteredAddress
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access private
   *
   * @param ?string $line1 the first address line
   * @param ?string $line2 the second address line
   * @param ?string $postalCode the postal code without national formatting assumptions
   * @param ?string $city the locality
   * @param ?string $region the region
   * @param ?OrganizationCountry $countryCode the ISO country
   *
   * @return void
   */
  private function __construct(
    public ?string $line1,
    public ?string $line2,
    public ?string $postalCode,
    public ?string $city,
    public ?string $region,
    public ?OrganizationCountry $countryCode,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray
   *
   * Creates a complete replacement from supplied components; omitted components become null.
   *
   * @access public
   *
   * @param array<string, ?string> $address the supplied address components
   *
   * @return self the normalized partial address
   */
  public static function fromArray(array $address): self
  {
    $countryCode = self::normalize($address['countryCode'] ?? null, 2);

    return new self(
      line1: self::normalize($address['line1'] ?? null, 255),
      line2: self::normalize($address['line2'] ?? null, 255),
      postalCode: self::normalize($address['postalCode'] ?? null, 32),
      city: self::normalize($address['city'] ?? null, 128),
      region: self::normalize($address['region'] ?? null, 128),
      countryCode: null !== $countryCode ? new OrganizationCountry($countryCode) : null,
    );
  }

  /**
   * Method toArray
   *
   * @access public
   *
   * @return array{line1: ?string, line2: ?string, postalCode: ?string, city: ?string, region: ?string, countryCode: ?string} the scalar address components
   */
  public function toArray(): array
  {
    return [
      'line1' => $this->line1,
      'line2' => $this->line2,
      'postalCode' => $this->postalCode,
      'city' => $this->city,
      'region' => $this->region,
      'countryCode' => $this->countryCode?->__toString(),
    ];
  }

  /**
   * Method isEmpty
   *
   * @access public
   *
   * @return bool whether no component is supplied
   */
  public function isEmpty(): bool
  {
    foreach ($this->toArray() as $value) {
      if (null !== $value) {
        return false;
      }
    }

    return true;
  }

  /**
   * Method normalize
   *
   * @access private
   *
   * @param ?string $value the raw component
   * @param int $maxLength the maximum number of characters
   *
   * @return ?string the trimmed component
   */
  private static function normalize(?string $value, int $maxLength): ?string
  {
    $value = null !== $value ? trim($value) : null;
    if (null !== $value && mb_strlen($value) > $maxLength) {
      throw InvalidValueException::because('Registered address component is too long.');
    }

    return '' === $value ? null : $value;
  }
  // #endregion
}

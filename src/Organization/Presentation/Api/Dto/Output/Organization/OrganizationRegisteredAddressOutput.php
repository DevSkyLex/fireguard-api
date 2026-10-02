<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class OrganizationRegisteredAddressOutput
 *
 * Exposes the registered office as scalar components, independent of facility addresses.
 *
 * @category DTO
 */
final readonly class OrganizationRegisteredAddressOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ?string $line1 the first address line
   * @param ?string $line2 the second address line
   * @param ?string $postalCode the postal code
   * @param ?string $city the locality
   * @param ?string $region the region
   * @param ?string $countryCode the normalized ISO country
   *
   * @return void
   */
  public function __construct(
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $line1 = null,
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $line2 = null,
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $postalCode = null,
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $city = null,
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $region = null,
    #[Groups([OrganizationSerializationGroup::READ])]
    #[ApiProperty(readable: true, writable: false)]
    public ?string $countryCode = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromArray
   *
   * @access public
   *
   * @param ?array{line1: ?string, line2: ?string, postalCode: ?string, city: ?string, region: ?string, countryCode: ?string} $address the scalar query result
   *
   * @return ?self the address output when present
   */
  public static function fromArray(?array $address): ?self
  {
    return null !== $address ? new self(...$address) : null;
  }
  // #endregion
}

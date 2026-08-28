<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\GeocodeAddress;

use Facility\Application\Port\Outbound\GeocodingPort;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAddressNotFoundException, FacilityNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * UseCase GeocodeAddressHandler.
 *
 * Resolves a free-form address to coordinates through {@see GeocodingPort}
 * as an aid to facility DATA ENTRY. The permission checked is therefore
 * `organization.facilities.write`, not `.read`: the only reason to geocode
 * an address here is to fill a facility's coordinates, and a read-only
 * member has no business burning the shared outbound geocoding budget.
 * Scope resolution mirrors {@see \Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler}:
 * outside-scope callers get the indistinguishable organization 404.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GeocodeAddressHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_ADDRESS_LENGTH.
   *
   * Upper bound on the submitted address, in characters. Free-form postal
   * addresses fit comfortably; anything longer is noise (or abuse) that
   * would be forwarded verbatim to the external provider.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_ADDRESS_LENGTH = 300;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param GeocodingPort $geocoding the geocoding port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private GeocodingPort $geocoding,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GeocodeAddressQuery $query the query to handle
   *
   * @throws FacilityNotFoundException when the caller is outside the organization's scope
   * @throws FacilityAccessDeniedException when the caller lacks `organization.facilities.write`
   * @throws InvalidValueException when the address is empty or longer than {@see self::MAX_ADDRESS_LENGTH} characters
   * @throws FacilityAddressNotFoundException when the provider knows no match for the address
   *
   * @return GeocodeAddressResult the best-match coordinates
   */
  public function __invoke(GeocodeAddressQuery $query): GeocodeAddressResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw FacilityNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing organization.facilities.write permission.');
    }

    $address = trim($query->address);
    if ('' === $address) {
      throw InvalidValueException::because('Address must not be empty.');
    }
    if (mb_strlen($address) > self::MAX_ADDRESS_LENGTH) {
      throw InvalidValueException::because(sprintf('Address must not exceed %d characters.', self::MAX_ADDRESS_LENGTH));
    }

    $result = $this->geocoding->geocode($address);
    if (null === $result) {
      throw FacilityAddressNotFoundException::forAddress();
    }

    return new GeocodeAddressResult(
      latitude: $result->latitude,
      longitude: $result->longitude,
      displayName: $result->displayName,
    );
  }
  // #endregion
}

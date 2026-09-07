<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\SuggestAddresses;

use Facility\Application\Port\Outbound\AddressSuggestionsPort;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAddressSuggestionsUnavailableException, FacilityNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * UseCase SuggestAddressesHandler.
 *
 * Resolves a free-form address to coordinates through {@see AddressSuggestionsPort}
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
final readonly class SuggestAddressesHandler implements QueryHandler
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
  public const int MAX_ADDRESS_LENGTH = 250;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AddressSuggestionsPort $suggestions the geocoding port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private AddressSuggestionsPort $suggestions,
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
   * @param SuggestAddressesQuery $query the query to handle
   *
   * @throws FacilityNotFoundException when the caller is outside the organization's scope
   * @throws FacilityAccessDeniedException when the caller lacks `organization.facilities.write`
   * @throws InvalidValueException when the address is shorter than three or longer than {@see self::MAX_ADDRESS_LENGTH} characters
   * @throws FacilityAddressSuggestionsUnavailableException when the provider cannot answer reliably
   *
   * @return SuggestAddressesResult the matching addresses
   */
  public function __invoke(SuggestAddressesQuery $query): SuggestAddressesResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw FacilityNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing organization.facilities.write permission.');
    }

    $address = trim($query->query);
    if (mb_strlen($address) < 3) {
      throw InvalidValueException::because('Address must contain at least 3 characters.');
    }
    if (mb_strlen($address) > self::MAX_ADDRESS_LENGTH) {
      throw InvalidValueException::because(sprintf('Address must not exceed %d characters.', self::MAX_ADDRESS_LENGTH));
    }

    return new SuggestAddressesResult($this->suggestions->suggest($address));
  }
  // #endregion
}

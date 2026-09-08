<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Application\UseCase\Query\SuggestAddresses\{SuggestAddressesQuery, SuggestAddressesResult};
use Facility\Presentation\Api\Dto\Output\Facility\{AddressSuggestionOutput, SuggestAddressesOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function array_map;
use function count;
use function hash;
use function is_string;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Provider SuggestAddressesProvider.
 *
 * Read-side of the address-geocoding input aid. Consumes the per-user
 * `facility_address_suggestions` rate limiter BEFORE any validation or dispatch — the
 * limiter protects the shared outbound Photon budget (and us), so even a
 * malformed request must burn from it. Permission
 * (`organization.facilities.write`, resolved through `resolveAccess`) is
 * enforced by {@see \Facility\Application\UseCase\Query\SuggestAddresses\SuggestAddressesHandler},
 * not here.
 *
 * Deliberately catch-free (FG-035): the handler's domain exceptions —
 * `FacilityNotFoundException` (404, outside scope),
 * `FacilityAccessDeniedException` (403),
 * `FacilityAddressSuggestionsUnavailableException` (503, provider unavailable),
 * `InvalidValueException` (400, short/too-long address) — are unwrapped by
 * `BusFailureUnwrappingSubscriber` and mapped centrally through
 * `api_platform.exception_to_status`.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SuggestAddressesOutput>
 */
final readonly class SuggestAddressesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security helper
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the per-user geocode rate limiter
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.facility_address_suggestions')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return SuggestAddressesOutput the address suggestions
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): SuggestAddressesOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $this->enforceRateLimit($user->getId());

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $address = $this->requestStack->getCurrentRequest()?->query->get('q');
    if (!is_string($address) || '' === $address) {
      throw new BadRequestHttpException('Address query parameter is required.');
    }

    /** @var SuggestAddressesResult $result */
    $result = $this->queryBus->ask(new SuggestAddressesQuery(
      userId: $user->getId(),
      organizationId: $organizationId,
      query: $address,
    ));

    $output = new SuggestAddressesOutput();
    $output->id = $organizationId;
    $output->member = array_map(static fn (AddressSuggestion $suggestion): AddressSuggestionOutput => new AddressSuggestionOutput(
      $suggestion->displayName,
      $suggestion->latitude,
      $suggestion->longitude,
      $suggestion->street,
      $suggestion->city,
      $suggestion->region,
      $suggestion->postalCode,
      $suggestion->country,
      $suggestion->countryCode,
    ), $result->suggestions);
    $output->totalItems = count($output->member);

    return $output;
  }

  /**
   * Method enforceRateLimit.
   *
   * Consumes one token from the per-user sliding window (30/min). Keyed by
   * user id, not IP: the endpoint is authenticated, so this bounds one
   * account without penalising everyone behind a shared address — mirrors
   * `invitation_accept`.
   *
   * @since 1.0.0
   *
   * @param string $userId the caller's user id
   *
   * @return void no return value
   */
  private function enforceRateLimit(string $userId): void
  {
    $limit = $this->rateLimiter->create('facility_address_suggestions_' . substr(hash('sha256', $userId), 0, 16))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many geocoding requests. Please try again in %d seconds.', $seconds),
    );
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\UseCase\Query\Organization\SearchOrganization\{SearchOrganizationQuery, SearchOrganizationResult};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationSearchHitOutput, OrganizationSearchOutput};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;
use function mb_strlen;
use function trim;

/**
 * Provider SearchOrganizationProvider.
 *
 * Unwraps the `q` query parameter (2..100 characters after trimming — 400
 * otherwise), dispatches the query, and maps the grouped Result into the
 * flat, typed `results` list. It decides nothing: membership (404 for a
 * non-member) and the per-type permission gating live in
 * `SearchOrganizationHandler`; its domain exceptions reach 404 through the
 * central `exception_to_status` mapping, like the sibling navigation
 * counters endpoint.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationSearchOutput>
 */
final readonly class SearchOrganizationProvider implements ProviderInterface
{
  // #region Constants
  /**
   * Constant MIN_TERM_LENGTH.
   *
   * @since 1.0.0
   */
  private const int MIN_TERM_LENGTH = 2;

  /**
   * Constant MAX_TERM_LENGTH.
   *
   * @since 1.0.0
   */
  private const int MAX_TERM_LENGTH = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
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
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrganizationSearchOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $rawTerm = $this->requestStack->getCurrentRequest()?->query->get('q');
    $term = is_string($rawTerm) ? trim($rawTerm) : '';
    $length = mb_strlen($term);
    if ($length < self::MIN_TERM_LENGTH || $length > self::MAX_TERM_LENGTH) {
      throw new BadRequestHttpException('The q parameter must be between 2 and 100 characters long.');
    }

    try {
      /** @var SearchOrganizationResult $result */
      $result = $this->queryBus->ask(new SearchOrganizationQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        term: $term,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      // Domain not-found failures inside the envelope reach 404 through
      // BusFailureUnwrappingSubscriber + exception_to_status; only the
      // handler's term-validation InvalidArgumentException needs a local
      // mapping to 400.
      $current = $exception->getPrevious();
      while (null !== $current) {
        if ($current instanceof InvalidArgumentException) {
          throw new BadRequestHttpException($current->getMessage(), $exception);
        }
        $current = $current->getPrevious();
      }

      throw $exception;
    }

    $output = new OrganizationSearchOutput();
    $output->query = $term;
    $output->results = [
      ...$this->mapHits('equipment', $result->equipments),
      ...$this->mapHits('facility', $result->facilities),
      ...$this->mapHits('intervention', $result->interventions),
      ...$this->mapHits('inspection', $result->inspections),
      ...$this->mapHits('non_conformity', $result->nonConformities),
    ];

    return $output;
  }

  /**
   * Method mapHits.
   *
   * @since 1.0.0
   *
   * @param string $type the result type literal
   * @param list<OrganizationSearchHit> $hits the hits of that type
   *
   * @return list<OrganizationSearchHitOutput> the mapped outputs
   */
  private function mapHits(string $type, array $hits): array
  {
    $outputs = [];
    foreach ($hits as $hit) {
      $output = new OrganizationSearchHitOutput();
      $output->type = $type;
      $output->id = $hit->id;
      $output->title = $hit->title;
      $output->subtitle = $hit->subtitle;
      $output->extra = $hit->extra;
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}

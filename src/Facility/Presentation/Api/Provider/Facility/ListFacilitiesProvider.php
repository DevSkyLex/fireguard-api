<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\Contract\Hierarchy\InterventionParentAccess;
use Facility\Application\Port\Outbound\InterventionScopePort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Provider ListFacilitiesProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityOutput>
 */
final readonly class ListFacilitiesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives query dispatch, organization authorization, caller identity, and request context for facility listing.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus port used to dispatch the facility-list query
   * @param OrganizationAuthorizationPort $authorization port used to authorize the organization-scoped read
   * @param Security $security security context used to obtain the requesting member
   * @param RequestStack $requestStack request context used to read collection filters and pagination
   * @param InterventionScopePort $interventions owns intervention preparation access
   *
   * @return void
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private InterventionScopePort $interventions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @return TraversablePaginator<FacilityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    $pagination = PaginationExtractor::fromContext($context);
    $query = $this->listQuery($operation, $context, $organizationId, $pagination->offset, $pagination->itemsPerPage, $decision->isGranted());
    if (null === $query->interventionId) {
      if (!$decision->isGranted()) {
        throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
      }
    } else {
      if (null === $query->parentForType && null === $query->parentForFacilityId) {
        throw new BadRequestHttpException('interventionId requires parentForType or parentForFacilityId.');
      }

      try {
        \Facility\Domain\ValueObject\FacilityId::fromString($query->interventionId);
      } catch (InvalidArgumentException $exception) {
        throw new BadRequestHttpException($exception->getMessage(), $exception);
      }
      $access = $this->interventions->preparationAccess($organizationId, $query->interventionId, $user->getId());
      if (InterventionParentAccess::NOT_FOUND === $access) {
        throw new NotFoundHttpException('Intervention not found.');
      }
      if (InterventionParentAccess::GRANTED !== $access) {
        throw new AccessDeniedHttpException('Intervention preparation access required.');
      }
    }

    try {
      /** @var PaginatedResult<GetFacilityResult> $result */
      $result = $this->queryBus->ask($query);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->items as $facility) {
      $outputs[] = $this->mapResult($facility);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * @param array<string, mixed> $context
   */
  private function listQuery(Operation $operation, array $context, string $organizationId, int $offset, int $itemsPerPage, bool $includePublishedParents): ListFacilitiesQuery
  {
    $query = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $this->requestStack->getCurrentRequest());
    $type = $query->get('type');
    $status = $query->get('status');
    $parentFacilityId = $query->get('parentFacilityId');
    $code = $query->get('code');
    $parentForType = $query->get('parentForType');
    $parentForFacilityId = $query->get('parentForFacilityId');
    $interventionId = $query->get('interventionId');

    return new ListFacilitiesQuery(
      organizationId: $organizationId,
      includeArchived: $query->getBoolean('includeArchived', false),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      type: is_string($type) && '' !== $type ? $type : null,
      status: is_string($status) && '' !== $status ? $status : null,
      parentFacilityId: is_string($parentFacilityId) && '' !== $parentFacilityId ? $parentFacilityId : null,
      rootsOnly: $query->getBoolean('rootsOnly', false),
      code: is_string($code) && '' !== $code ? $code : null,
      hasCoordinates: $query->has('hasCoordinates') ? $query->getBoolean('hasCoordinates') : null,
      search: SearchExtractor::fromContext($context),
      sorting: SortingExtractor::fromContext($context, ['name', 'type', 'status', 'createdAt', 'updatedAt', 'code'], 'name'),
      includePath: $query->getBoolean('includePath', false),
      parentForType: is_string($parentForType) && '' !== $parentForType ? $parentForType : null,
      parentForFacilityId: is_string($parentForFacilityId) && '' !== $parentForFacilityId ? $parentForFacilityId : null,
      interventionId: is_string($interventionId) && '' !== $interventionId ? $interventionId : null,
      includePublishedParents: $includePublishedParents,
    );
  }

  /**
   * Method findInvalidArgumentException.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   *
   * @return ?InvalidArgumentException the resolved exception
   */
  private function findInvalidArgumentException(Throwable $exception): ?InvalidArgumentException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof InvalidArgumentException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof InvalidArgumentException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method mapResult.
   *
   * @since 1.0.0
   */
  private function mapResult(GetFacilityResult $facility): FacilityOutput
  {
    $output = new FacilityOutput();
    $output->id = $facility->facilityId;
    $output->organizationId = $facility->organizationId;
    $output->parentFacilityId = $facility->parentFacilityId;
    $output->hasChildren = $facility->hasChildren;
    $output->equipmentCount = $facility->equipmentCount;
    $output->path = $facility->path;
    $output->hierarchyIssues = $facility->hierarchyIssues;
    $output->recordStatus = $facility->recordStatus;
    $output->intervention = null === $facility->interventionId ? null : '/api/interventions/' . $facility->interventionId;
    $output->revision = $facility->revision;
    $output->type = $facility->type;
    $output->name = $facility->name;
    $output->code = $facility->code;
    $output->status = $facility->status;
    $output->address = $facility->address;
    $output->latitude = $facility->latitude;
    $output->longitude = $facility->longitude;
    $output->metadata = $facility->metadata;
    $output->levelIndex = $facility->levelIndex;
    $output->elevationMeters = $facility->elevationMeters;
    $output->heightMeters = $facility->heightMeters;
    $output->createdAt = $facility->createdAt->format('c');
    $output->updatedAt = $facility->updatedAt->format('c');

    return $output;
  }
  // #endregion
}

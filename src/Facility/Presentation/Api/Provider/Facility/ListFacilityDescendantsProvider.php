<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityDescendants\{GetFacilityDescendantsQuery, GetFacilityDescendantsResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/** @implements ProviderInterface<FacilityOutput> */
final readonly class ListFacilityDescendantsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies query dispatch, organization authorization, authenticated user access, and request context.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus query dispatcher
   * @param OrganizationAuthorizationPort $authorization organization permission resolver
   * @param Security $security current authenticated user accessor
   * @param RequestStack $requestStack current request access for query parameters
   *
   * @return void
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method provide
   *
   * Authorizes and returns the requested facility descendants with search, sorting, and archive filters.
   *
   * @access public
   *
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables route variables containing organizationId and facilityId
   * @param array<string, mixed> $context provider context for collection filters
   *
   * @return list<FacilityOutput>|TraversablePaginator<FacilityOutput>
   *
   * @throws AccessDeniedHttpException when the caller is unauthenticated or lacks permission
   * @throws BadRequestHttpException when required route variables or filters are invalid
   * @throws NotFoundHttpException when the facility is outside the visible organization scope
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|TraversablePaginator
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $includeArchived = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->getBoolean('includeArchived', false);
    $parameters = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request);
    $paginated = $parameters->getBoolean('pagination', false);
    $page = max(1, $parameters->getInt('page', 1));
    $itemsPerPage = min(100, max(1, $parameters->getInt('itemsPerPage', 30)));
    $filters = \Shared\Presentation\Api\Http\OperationParameterReader::filters($operation, $context);

    try {
      /** @var GetFacilityDescendantsResult $result */
      $result = $this->queryBus->ask(new GetFacilityDescendantsQuery(
        organizationId: $organizationId,
        facilityId: $facilityId,
        includeArchived: $includeArchived,
        search: SearchExtractor::fromContext(['filters' => $filters]),
        sorting: SortingExtractor::fromContext(['filters' => $filters], ['name', 'type', 'status', 'createdAt', 'code'], 'name'),
        pagination: $paginated ? new Pagination(limit: $itemsPerPage, offset: ($page - 1) * $itemsPerPage) : null,
        includePath: $parameters->getBoolean('includePath', false),
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findFacilityNotFoundException($exception);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = array_map($this->mapResult(...), $result->items);
    if (!$paginated) {
      return $outputs;
    }

    return new TraversablePaginator(
      new ArrayIterator($outputs),
      (float) $page,
      (float) $itemsPerPage,
      (float) ($result->total ?? 0),
    );
  }

  /**
   * Method findFacilityNotFoundException
   *
   * Searches nested handler exceptions for a facility-not-found error.
   *
   * @access private
   *
   * @param Throwable $exception exception chain to inspect
   *
   * @return FacilityNotFoundException|null matching exception, or null when absent
   */
  private function findFacilityNotFoundException(Throwable $exception): ?FacilityNotFoundException
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof FacilityNotFoundException) {
        return $current;
      }

      if ($current instanceof HandlerFailedException) {
        foreach ($current->getWrappedExceptions() as $wrappedException) {
          if ($wrappedException instanceof FacilityNotFoundException) {
            return $wrappedException;
          }
        }
      }

      $current = $current->getPrevious();
    }

    return null;
  }

  /**
   * Method findInvalidArgumentException
   *
   * Searches nested handler exceptions for an invalid-argument error.
   *
   * @access private
   *
   * @param Throwable $exception exception chain to inspect
   *
   * @return InvalidArgumentException|null matching exception, or null when absent
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
   * Method mapResult
   *
   * Maps a facility query result to the API output DTO.
   *
   * @access private
   *
   * @param GetFacilityResult $facility facility result to map
   *
   * @return FacilityOutput serialized API output data
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
    // See ListFacilityChildrenProvider: the tree endpoints must return the same
    // fields as the flat list, coordinates included.
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

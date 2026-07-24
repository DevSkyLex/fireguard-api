<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\GetFacilityChildren\GetFacilityChildrenQuery;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_numeric;
use function is_string;
use function max;

/** @implements ProviderInterface<FacilityOutput> */
final readonly class ListFacilityChildrenProvider implements ProviderInterface
{
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * @return TraversablePaginator<FacilityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.facilities.read')) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $includeArchived = $request?->query->getBoolean('includeArchived', false) ?? false;

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    try {
      /** @var PaginatedResult<GetFacilityResult> $result */
      $result = $this->queryBus->ask(new GetFacilityChildrenQuery(
        organizationId: $organizationId,
        facilityId: $facilityId,
        includeArchived: $includeArchived,
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
        search: SearchExtractor::fromContext($context),
        sorting: SortingExtractor::fromContext($context, ['name', 'type', 'status', 'createdAt', 'code'], 'name'),
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

    $outputs = [];
    foreach ($result->items as $facility) {
      $outputs[] = $this->mapResult($facility);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

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

  private function mapResult(GetFacilityResult $facility): FacilityOutput
  {
    $output = new FacilityOutput();
    $output->id = $facility->facilityId;
    $output->organizationId = $facility->organizationId;
    $output->parentFacilityId = $facility->parentFacilityId;
    $output->hasChildren = $facility->hasChildren;
    $output->equipmentCount = $facility->equipmentCount;
    $output->type = $facility->type;
    $output->name = $facility->name;
    $output->code = $facility->code;
    $output->status = $facility->status;
    $output->address = $facility->address;
    // Same mapping as the flat list: a facility read through the tree must not
    // come back without its coordinates, or a client that edits it round-trips
    // a null and erases them.
    $output->latitude = $facility->latitude;
    $output->longitude = $facility->longitude;
    $output->metadata = $facility->metadata;
    $output->createdAt = $facility->createdAt->format('c');
    $output->updatedAt = $facility->updatedAt->format('c');

    return $output;
  }
}

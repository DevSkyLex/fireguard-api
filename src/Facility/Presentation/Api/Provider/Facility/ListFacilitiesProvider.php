<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesQuery, ListFacilitiesResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Provider ListFacilitiesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityOutput>
 */
final readonly class ListFacilitiesProvider implements ProviderInterface
{
  // #region Constructor
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
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<FacilityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.facilities.read')) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $includeArchived = $request?->query->getBoolean('includeArchived', false) ?? false;

    try {
      /** @var ListFacilitiesResult $result */
      $result = $this->queryBus->ask(new ListFacilitiesQuery(
        organizationId: $organizationId,
        includeArchived: $includeArchived,
      ));
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
    foreach ($result->facilities as $facility) {
      $outputs[] = $this->mapResult($facility);
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name', 'type', 'code', 'status', 'address']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'type', 'status', 'createdAt'], 'name');

    return CollectionSorter::sort($outputs, $sorting);
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
    $output->type = $facility->type;
    $output->name = $facility->name;
    $output->code = $facility->code;
    $output->status = $facility->status;
    $output->address = $facility->address;
    $output->metadata = $facility->metadata;
    $output->createdAt = $facility->createdAt->format('c');
    $output->updatedAt = $facility->updatedAt->format('c');

    return $output;
  }
  // #endregion
}

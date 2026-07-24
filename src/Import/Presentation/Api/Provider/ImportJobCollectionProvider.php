<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Import\Application\UseCase\Query\ListImportJobs\{ListImportJobsQuery, ListImportJobsResult};
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Trait\ImportExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider ImportJobCollectionProvider.
 *
 * Handles `GET /imports` (org-scoped list, optional `kind` filter).
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ImportJobOutput>
 */
final readonly class ImportJobCollectionProvider implements ProviderInterface
{
  use ImportExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param ImportJobOutputFactory $outputFactory the output factory value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private ImportJobOutputFactory $outputFactory,
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
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return object the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }

    $kind = $query?->get('kind');
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListImportJobsResult $result */
      $result = $this->queryBus->ask(new ListImportJobsQuery(
        userId: $user->getId(),
        organizationId: ResourceIriParser::id($organization, 'organizations'),
        kind: is_string($kind) && '' !== $kind ? $kind : null,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapImportException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->outputFactory->fromView(...), $result->items)),
      (float) $result->page,
      (float) $result->itemsPerPage,
      (float) $result->total,
    );
  }
  // #endregion
}

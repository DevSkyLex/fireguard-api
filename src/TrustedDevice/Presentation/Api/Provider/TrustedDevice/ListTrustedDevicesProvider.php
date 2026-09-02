<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Provider\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\{ListTrustedDevicesQuery, TrustedDeviceItemResult};
use TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice\TrustedDeviceOutput;

use function array_slice;
use function count;
use function is_numeric;
use function max;

/**
 * Provider ListTrustedDevicesProvider.
 *
 * @implements ProviderInterface<TrustedDeviceOutput>
 */
final readonly class ListTrustedDevicesProvider implements ProviderInterface
{
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }

  /**
   * @return TraversablePaginator<TrustedDeviceOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    if (!$user instanceof SecurityUser) {
      throw new BadRequestHttpException('Authenticated user type is not supported.');
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    /** @var PaginatedResult<TrustedDeviceItemResult> $result */
    $result = $this->queryBus->ask(new ListTrustedDevicesQuery(
      userId: $user->getId(),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
    ));

    $outputs = [];
    foreach ($result->items as $device) {
      $output = new TrustedDeviceOutput();
      $output->id = $device->id;
      $output->name = $device->name;
      $output->lastUsedAt = $device->lastUsedAt;
      $output->expiresAt = $device->expiresAt;
      $output->createdAt = $device->createdAt;
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['name', 'lastUsedAt', 'expiresAt', 'createdAt'], 'createdAt', SortDirection::DESC);
    $outputs = CollectionSorter::sort($outputs, $sorting);

    $outputs = array_slice($outputs, $offset, $itemsPerPage);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $total,
    );
  }
}

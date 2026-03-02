<?php

declare(strict_types=1);

namespace TrustedDevice\Presentation\Api\Provider\TrustedDevice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\{ListTrustedDevicesQuery, ListTrustedDevicesResult};
use TrustedDevice\Presentation\Api\Dto\Output\TrustedDevice\TrustedDeviceOutput;

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
   * @return list<TrustedDeviceOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $query = new ListTrustedDevicesQuery(userId: $user->getUserIdentifier());
    /** @var ListTrustedDevicesResult $result */
    $result = $this->queryBus->ask($query);

    $outputs = [];
    foreach ($result->devices as $device) {
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

    $sorting = SortingExtractor::fromContext($context, ['name', 'lastUsedAt', 'expiresAt', 'createdAt'], 'createdAt', SortDirection::DESC);

    return CollectionSorter::sort($outputs, $sorting);
  }
}

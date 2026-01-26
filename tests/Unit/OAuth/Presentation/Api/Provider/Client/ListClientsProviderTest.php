<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Application\UseCase\Query\Client\ListClients\ListClientsQuery;
use OAuth\Presentation\Api\Provider\Client\ListClientsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;

use function iterator_to_array;

/**
 * Test ListClientsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListClientsProvider::class)]
final class ListClientsProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsClientsAndPagination(): void
  {
    $client = new GetClientResult(
      id: 'client-123',
      name: 'Test Client',
      redirectUris: ['https://client.example.com/callback'],
      grantTypes: ['authorization_code'],
      scopes: ['openid'],
      isActive: true,
      createdAt: '2024-01-01T00:00:00+00:00',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        static fn (ListClientsQuery $query): bool => 1 === $query->pagination->offset
          && 1 === $query->pagination->limit,
      ))
      ->willReturn(new PaginatedResult(
        items: [$client],
        total: 2,
        limit: 1,
        offset: 1,
      ));

    $provider = new ListClientsProvider(queryBus: $queryBus);

    $result = $provider->provide(
      operation: $this->createMock(Operation::class),
      context: [
        'filters' => [
          'page' => '2',
          'itemsPerPage' => '1',
        ],
      ],
    );

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(2.0, $result->getCurrentPage());
    self::assertSame(1.0, $result->getItemsPerPage());
    self::assertSame(2.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertSame('client-123', $items[0]->id);
  }
  // #endregion
}

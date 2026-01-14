<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Client\ListClients;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Application\UseCase\Query\Client\ListClients\{
  ListClientsHandler,
  ListClientsQuery
};
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{
  PaginatedResult,
  Pagination
};
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test ListClientsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListClientsHandler::class)]
final class ListClientsHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsPaginatedResult.
   *
   * Test that __invoke returns paginated result
   * with clients
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeReturnsPaginatedResult(): void
  {
    // Create real client
    $client = Client::register(
      id: new ClientId('123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName('Test Client'),
      secret: new ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );

    // Mocks
    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findAll')
      ->with(self::equalTo(0), self::equalTo(10))
      ->willReturn([$client]);
    $repository->expects(self::once())
      ->method('count')
      ->willReturn(1);

    // Query
    $query = new ListClientsQuery(pagination: new Pagination(offset: 0, limit: 10));

    // Handler
    $handler = new ListClientsHandler(clientRepository: $repository);

    // Execute
    $result = $handler->__invoke($query);

    // Assert
    self::assertInstanceOf(expected: PaginatedResult::class, actual: $result);
    self::assertSame(expected: 1, actual: $result->total);
    self::assertCount(expectedCount: 1, haystack: $result->items);
    self::assertInstanceOf(expected: GetClientResult::class, actual: $result->items[0]);
    self::assertSame(expected: 'Test Client', actual: $result->items[0]->name);
  }
  // #endregion
}

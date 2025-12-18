<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\ListClients;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Application\UseCase\Query\ListClients\{
  ListClientsHandler,
  ListClientsQuery
};
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\{
  ClientId,
  ClientName,
  ClientSecret
};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Query\{
  PaginatedResult,
  Pagination
};
use OAuth\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};
use Tests\Helper\TestEventIdProvider;

/**
 * Test ListClientsHandlerTest
 * @final
 *
 * Test class for ListClientsHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Query\ListClients
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListClientsHandler::class)]
final class ListClientsHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeReturnsPaginatedResult
   *
   * Test that __invoke returns paginated result
   * with clients
   *
   * @access public
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
  //#endregion
}


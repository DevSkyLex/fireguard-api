<?php

declare(strict_types=1);

namespace Tests\Client\Application\UseCase\Query\GetClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Application\UseCase\Query\GetClient\{
  GetClientHandler,
  GetClientQuery,
  GetClientResult
};
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\{
  ClientId,
  ClientName
};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\EntityNotFoundException;
use Shared\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};

/**
 * Test GetClientHandlerTest
 * @final
 *
 * Test class for GetClientHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Query\GetClient
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetClientHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeReturnsClientResult
   *
   * Test that __invoke returns client result
   * when client is found
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvokeReturnsClientResult(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Test Client'),
      secret: new \Client\Domain\ValueObject\ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(new GrantType('authorization_code')),
      scopes: new Scopes(new Scope('read'))
    );

    // Mocks
    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::equalTo(new ClientId($clientId)))
      ->willReturn($client);

    // Query
    $query = new GetClientQuery(clientId: $clientId);

    // Handler
    $handler = new GetClientHandler(clientRepository: $repository);

    // Execute
    $result = $handler->__invoke($query);

    // Assert
    self::assertInstanceOf(expected: GetClientResult::class, actual: $result);
    self::assertSame(expected: $clientId, actual: $result->id);
    self::assertSame(expected: 'Test Client', actual: $result->name);
    self::assertTrue(condition: $result->isActive);
  }

  /**
   * Method testInvokeThrowsExceptionWhenClientNotFound
   *
   * Test that __invoke throws exception
   * when client is not found
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvokeThrowsExceptionWhenClientNotFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $query = new GetClientQuery(clientId: $clientId);

    $handler = new GetClientHandler(clientRepository: $repository);

    $this->expectException(EntityNotFoundException::class);
    $handler->__invoke($query);
  }
  //#endregion
}

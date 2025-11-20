<?php

declare(strict_types=1);

namespace Tests\Client\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use Client\Application\UseCase\Query\GetClient\GetClientQuery;
use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Client\Presentation\Api\Provider\GetClientProvider;
use Client\Presentation\Api\Resource\ClientResource;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Test GetClientProviderTest
 * @final
 *
 * Test class for GetClientProvider.
 *
 * @category Provider Tests
 * @package Tests\Client\Presentation\Api\Provider
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetClientProviderTest extends TestCase
{
  //#region Methods
  /**
   * Method testProvideReturnsClientResourceWhenFound
   *
   * Test that provide returns client resource when found.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testProvideReturnsClientResourceWhenFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $result = new GetClientResult(
      id: $clientId,
      name: 'Test Client',
      redirectUris: ['https://example.com'],
      grantTypes: ['authorization_code'],
      scopes: ['read'],
      isActive: true,
      createdAt: '2023-01-01T00:00:00+00:00'
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn($result);

    $operation = $this->createMock(Operation::class);

    $provider = new GetClientProvider(queryBus: $queryBus);

    $resource = $provider->provide(
      operation: $operation,
      uriVariables: ['id' => $clientId]
    );

    self::assertInstanceOf(expected: ClientResource::class, actual: $resource);
    self::assertSame(expected: $clientId, actual: $resource->id);
    self::assertSame(expected: 'Test Client', actual: $resource->name);
  }

  /**
   * Method testProvideReturnsNullWhenNotFound
   *
   * Test that provide returns null when not found.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testProvideReturnsNullWhenNotFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new EntityNotFoundException('Client not found'));

    $operation = $this->createMock(Operation::class);

    $provider = new GetClientProvider(queryBus: $queryBus);

    $resource = $provider->provide(
      operation: $operation,
      uriVariables: ['id' => $clientId]
    );

    self::assertNull(actual: $resource);
  }
  //#endregion
}

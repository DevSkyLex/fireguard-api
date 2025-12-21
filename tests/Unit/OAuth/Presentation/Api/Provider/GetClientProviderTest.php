<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use OAuth\Application\UseCase\Query\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use OAuth\Presentation\Api\Provider\Client\GetClientProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Test GetClientProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GetClientProvider::class)]
final class GetClientProviderTest extends TestCase
{
  // #region Methods
  /**
   * Method testProvideReturnsClientOutputWhenFound.
   *
   * Test that provide returns client output when found.
   *
   * @return void No return value
   */
  #[Test]
  public function testProvideReturnsClientOutputWhenFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $result = new GetClientResult(
      id: $clientId,
      name: 'Test Client',
      redirectUris: ['https://example.com'],
      grantTypes: ['authorization_code'],
      scopes: ['read'],
      isActive: true,
      createdAt: '2023-01-01T00:00:00+00:00',
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn($result);

    $operation = $this->createMock(Operation::class);

    $provider = new GetClientProvider(queryBus: $queryBus);

    $output = $provider->provide(
      operation: $operation,
      uriVariables: ['id' => $clientId],
    );

    self::assertInstanceOf(expected: ClientOutput::class, actual: $output);
    self::assertSame(expected: $clientId, actual: $output->id);
    self::assertSame(expected: 'Test Client', actual: $output->name);
  }

  /**
   * Method testProvideReturnsNullWhenNotFound.
   *
   * Test that provide returns null when not found.
   *
   * @return void No return value
   */
  #[Test]
  public function testProvideReturnsNullWhenNotFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new EntityNotFoundException('Client not found'));

    $operation = $this->createMock(Operation::class);

    $provider = new GetClientProvider(queryBus: $queryBus);

    $output = $provider->provide(
      operation: $operation,
      uriVariables: ['id' => $clientId],
    );

    self::assertNull(actual: $output);
  }
  // #endregion
}

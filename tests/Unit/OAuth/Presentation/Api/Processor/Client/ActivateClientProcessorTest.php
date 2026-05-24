<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use OAuth\Presentation\Api\Processor\Client\ActivateClientProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};

/**
 * Test ActivateClientProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ActivateClientProcessor::class)]
final class ActivateClientProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new ActivateClientProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 123],
    );
  }

  #[Test]
  public function testProcessDispatchesAndReturnsOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ActivateClientCommand $command): bool => 'client-123' === $command->clientId,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn(self::createClientResult(isActive: true));

    $processor = new ActivateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );

    self::assertInstanceOf(ClientOutput::class, $output);
    self::assertSame('client-123', $output->id);
    self::assertTrue($output->isActive);
  }

  private static function createClientResult(bool $isActive): GetClientResult
  {
    return new GetClientResult(
      id: 'client-123',
      name: 'Test Client',
      redirectUris: ['https://client.example.com/callback'],
      grantTypes: ['authorization_code'],
      scopes: ['openid'],
      isActive: $isActive,
      createdAt: '2024-01-01T00:00:00+00:00',
    );
  }
  // #endregion
}

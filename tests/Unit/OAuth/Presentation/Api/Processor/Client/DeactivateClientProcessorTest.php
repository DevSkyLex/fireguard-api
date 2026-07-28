<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\DeactivateClient\DeactivateClientCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\ValueObject\Client\ClientId;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use OAuth\Presentation\Api\Processor\Client\DeactivateClientProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Test DeactivateClientProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeactivateClientProcessor::class)]
final class DeactivateClientProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new DeactivateClientProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => null],
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
        static fn (DeactivateClientCommand $command): bool => 'client-123' === $command->clientId,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn(self::createClientResult(isActive: false));

    $processor = new DeactivateClientProcessor(
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
    self::assertFalse($output->isActive);
  }

  #[Test]
  public function testProcessMapsInvalidClientExceptionToNotFound(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(InvalidClientException::forId(self::clientId()));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new DeactivateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidClientExceptionFromMessengerFailure(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(
        InvalidClientException::forId(self::clientId()),
      ));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new DeactivateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidClientExceptionFromAHandlerFailure(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new stdClass()),
        [new RuntimeException('unrelated'), InvalidClientException::forId(self::clientId())],
      )));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new DeactivateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('is invalid or not found');

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWithoutInvalidClientCause(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new stdClass()),
        [new RuntimeException('transport down')],
      )));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $processor = new DeactivateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );
  }

  private static function clientId(): ClientId
  {
    return new ClientId(value: '550e8400-e29b-41d4-a716-446655440900');
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

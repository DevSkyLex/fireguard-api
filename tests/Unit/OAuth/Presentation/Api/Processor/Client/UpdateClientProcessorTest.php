<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use OAuth\Presentation\Api\Dto\Input\Client\ClientInput;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use OAuth\Presentation\Api\Processor\Client\UpdateClientProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};

use function count;

/**
 * Test UpdateClientProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UpdateClientProcessor::class)]
final class UpdateClientProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new UpdateClientProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: new ClientInput(),
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => []],
    );
  }

  #[Test]
  public function testProcessDispatchesAndReturnsOutput(): void
  {
    $input = new ClientInput();
    $input->name = 'Updated Client';
    $input->redirectUris = ['https://client.example.com/callback'];
    $input->scopes = ['READ'];

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static function (UpdateClientDetailsCommand $command): bool {
          if ('client-123' !== $command->clientId || 'Updated Client' !== $command->name) {
            return false;
          }

          if (1 !== count($command->redirectUris)) {
            return false;
          }

          if ('https://client.example.com/callback' !== $command->redirectUris[0]->value) {
            return false;
          }

          return ['READ'] === $command->scopes->toArray();
        },
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetClientQuery::class))
      ->willReturn(self::createClientResult());

    $processor = new UpdateClientProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(
      data: $input,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );

    self::assertInstanceOf(ClientOutput::class, $output);
    self::assertSame('client-123', $output->id);
    self::assertSame('Updated Client', $output->name);
  }

  private static function createClientResult(): GetClientResult
  {
    return new GetClientResult(
      id: 'client-123',
      name: 'Updated Client',
      redirectUris: ['https://client.example.com/callback'],
      grantTypes: ['authorization_code'],
      scopes: ['READ'],
      isActive: true,
      createdAt: '2024-01-01T00:00:00+00:00',
    );
  }
  // #endregion
}

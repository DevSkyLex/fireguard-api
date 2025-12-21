<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use OAuth\Application\UseCase\Command\RegisterClient\RegisterClientCommand;
use OAuth\Application\UseCase\Command\RegisterClient\RegisterClientResult;
use OAuth\Presentation\Api\Dto\Input\ClientInput;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use OAuth\Presentation\Api\Processor\RegisterClientProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test RegisterClientProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RegisterClientProcessor::class)]
final class RegisterClientProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessRegistersClientAndReturnsOutput.
   *
   * Test that process registers client
   * and returns output.
   *
   * @return void No return value
   */
  #[Test]
  public function testProcessRegistersClientAndReturnsOutput(): void
  {
    // Data
    $input = new ClientInput();
    $input->name = 'Test Client';
    $input->redirectUris = ['https://example.com'];
    $input->grantTypes = ['AUTHORIZATION_CODE'];
    $input->scopes = ['READ'];

    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $clientSecret = 'plain_secret';

    $result = new RegisterClientResult(
      clientId: $clientId,
      clientSecret: $clientSecret,
    );

    // Mocks
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(RegisterClientCommand::class))
      ->willReturn($result);

    $operation = $this->createMock(Operation::class);

    // Processor
    $processor = new RegisterClientProcessor(commandBus: $commandBus);

    // Execute
    $output = $processor->process(
      data: $input,
      operation: $operation,
    );

    // Assert
    self::assertInstanceOf(ClientOutput::class, $output);
    self::assertSame(expected: $clientId, actual: $output->id);
    self::assertSame(expected: $clientSecret, actual: $output->secret);
    self::assertSame(expected: 'Test Client', actual: $output->name);
    self::assertNotNull(actual: $output->createdAt);
  }
  // #endregion
}

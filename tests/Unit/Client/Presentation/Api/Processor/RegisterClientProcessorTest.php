<?php

declare(strict_types=1);

namespace Tests\Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use Client\Application\UseCase\Command\RegisterClient\RegisterClientCommand;
use Client\Application\UseCase\Command\RegisterClient\RegisterClientResult;
use Client\Presentation\Api\Processor\RegisterClientProcessor;
use Client\Presentation\Api\Resource\ClientResource;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test RegisterClientProcessorTest
 * @final
 *
 * Test class for RegisterClientProcessor.
 *
 * @category Processor Tests
 * @package Tests\Client\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RegisterClientProcessorTest extends TestCase
{
  //#region Methods
  /**
   * Method testProcessRegistersClientAndUpdatesResource
   *
   * Test that process registers client and updates resource.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testProcessRegistersClientAndUpdatesResource(): void
  {
    // Data
    $resource = new ClientResource();
    $resource->name = 'Test Client';
    $resource->redirectUris = ['https://example.com'];
    $resource->grantTypes = ['authorization_code'];
    $resource->scopes = ['read'];

    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $clientSecret = 'plain_secret';

    $result = new RegisterClientResult(
      clientId: $clientId,
      clientSecret: $clientSecret
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
    $processedResource = $processor->process(
      data: $resource,
      operation: $operation
    );

    // Assert
    self::assertSame(expected: $clientId, actual: $processedResource->id);
    self::assertSame(expected: $clientSecret, actual: $processedResource->secret);
    self::assertNotNull(actual: $processedResource->createdAt);
  }
  //#endregion
}


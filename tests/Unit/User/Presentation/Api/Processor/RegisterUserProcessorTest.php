<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\RegisterUser\RegisterUserCommand;
use User\Application\UseCase\Command\RegisterUser\RegisterUserResult;
use User\Presentation\Api\Processor\RegisterUserProcessor;
use User\Presentation\Api\Resource\UserResource;

/**
 * Test RegisterUserProcessorTest
 * @final
 *
 * Unit tests for the RegisterUserProcessor.
 *
 * @category Processor Tests
 * @package Tests\Unit\User\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RegisterUserProcessor::class)]
final class RegisterUserProcessorTest extends TestCase
{
  //#region Properties
  /**
   * Property commandBus
   *
   * Mock of the command bus.
   *
   * @access private
   *
   * @var CommandBusPort&MockObject
   */
  private CommandBusPort&MockObject $commandBus;

  /**
   * Property processor
   *
   * The processor under test.
   *
   * @access private
   *
   * @var RegisterUserProcessor
   */
  private RegisterUserProcessor $processor;
  //#endregion

  //#region Setup
  /**
   * Method setUp
   *
   * Set up the test environment.
   *
   * @access protected
   *
   * @return void No return value.
   */
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new RegisterUserProcessor($this->commandBus);
  }
  //#endregion

  //#region Methods
  /**
   * Method testProcessesRegistrationRequest
   *
   * Tests that the processor processes
   * a registration request successfully.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessesRegistrationRequest(): void
  {
    // Arrange
    $resource = new UserResource();
    $resource->username = 'jdoe';
    $resource->email = 'jdoe@example.com';
    $resource->password = 'password123';
    $resource->firstName = 'John';
    $resource->lastName = 'Doe';

    $result = new RegisterUserResult('user-id-123');

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(RegisterUserCommand::class))
      ->willReturn($result);

    $operation = new Post();

    // Act
    $processedResource = $this->processor->process($resource, $operation);

    // Assert
    $this->assertInstanceOf(UserResource::class, $processedResource);
    $this->assertEquals('user-id-123', $processedResource->id);
  }
  //#endregion
}

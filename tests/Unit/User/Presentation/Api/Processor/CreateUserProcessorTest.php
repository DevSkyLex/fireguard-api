<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\CreateUser\CreateUserCommand;
use User\Application\UseCase\Command\CreateUser\CreateUserResult;
use User\Presentation\Api\Dto\UserInput;
use User\Presentation\Api\Dto\UserOutput;
use User\Presentation\Api\Processor\CreateUserProcessor;

/**
 * Test CreateUserProcessorTest.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateUserProcessor::class)]
final class CreateUserProcessorTest extends TestCase
{
    // #region Properties
    /**
     * Property commandBus.
     *
     * Mock of the command bus.
     */
    private CommandBusPort&MockObject $commandBus;

    /**
     * Property processor.
     *
     * The processor under test.
     */
    private CreateUserProcessor $processor;
    // #endregion

    // #region Setup
    /**
     * Method setUp.
     *
     * Set up the test environment.
     *
     * @return void no return value
     */
    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusPort::class);
        $this->processor = new CreateUserProcessor($this->commandBus);
    }
    // #endregion

    // #region Methods
    /**
     * Method testProcessesCreationRequest.
     *
     * Tests that the processor processes
     * a creation request successfully.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testProcessesCreationRequest(): void
    {
        // Arrange
        $input = new UserInput();
        $input->username = 'jdoe';
        $input->email = 'jdoe@example.com';
        $input->password = 'password123';
        $input->firstName = 'John';
        $input->lastName = 'Doe';

        $result = new CreateUserResult('user-id-123');

        $this->commandBus->expects($this->once())
          ->method('dispatch')
          ->with($this->isInstanceOf(CreateUserCommand::class))
          ->willReturn($result);

        $operation = new Post();

        // Act
        $output = $this->processor->process($input, $operation);

        // Assert
        $this->assertInstanceOf(UserOutput::class, $output);
        $this->assertEquals('user-id-123', $output->id);
        $this->assertEquals('jdoe', $output->username);
    }
    // #endregion
}

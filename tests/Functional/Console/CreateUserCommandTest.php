<?php

declare(strict_types=1);

namespace Tests\Functional\Console;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;
use User\Application\UseCase\Command\CreateUser\CreateUserCommand;

use function get_class;
use function uniqid;

/**
 * Test CreateUserCommandTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateUserCommandTest extends KernelTestCase
{
    // #region Properties
    /**
     * Property application.
     *
     * The console application.
     */
    private ?Application $application = null;
    // #endregion

    // #region Methods
    /**
     * Method setUp.
     *
     * Sets up the test environment.
     *
     * @return void No return value
     */
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application(
            kernel: $kernel
        );
    }

    /**
     * Method testCommandExists.
     *
     * Tests that the command exists.
     *
     * @return void No return value
     */
    public function testCommandExists(): void
    {
        $this->assertTrue(condition: $this->application?->has(
            name: 'app:user:create'
        ));
    }

    /**
     * Method testCommandHasAlias.
     *
     * Tests that the command has an alias.
     *
     * @return void No return value
     */
    public function testCommandHasAlias(): void
    {
        $this->assertTrue(condition: $this->application?->has(
            name: 'user:create'
        ));
    }

    /**
     * Method testCommandRequiresEmail.
     *
     * Tests that the command requires an email argument.
     *
     * @return void No return value
     */
    public function testCommandRequiresEmail(): void
    {
        $command = $this->application?->find(name: 'app:user:create');
        $this->assertNotNull(actual: $command);

        $commandTester = new CommandTester(command: $command);

        $this->expectException(exception: RuntimeException::class);
        $this->expectExceptionMessage(message: 'Not enough arguments');

        $commandTester->execute(input: []);
    }

    /**
     * Method testCommandHasUsernameOption.
     *
     * Tests that the command has
     * a username option.
     */
    public function testCommandHasUsernameOption(): void
    {
        $command = $this->application?->find('app:user:create');
        $this->assertNotNull($command);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('username'));
    }

    /**
     * Method testCommandHasFirstNameOption.
     *
     * Tests that the command has a first-name option.
     */
    public function testCommandHasFirstNameOption(): void
    {
        $command = $this->application?->find('app:user:create');
        $this->assertNotNull($command);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('first-name'));
    }

    /**
     * Method testCommandHasLastNameOption.
     *
     * Tests that the command has a last-name option.
     */
    public function testCommandHasLastNameOption(): void
    {
        $command = $this->application?->find('app:user:create');
        $this->assertNotNull($command);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('last-name'));
    }

    /**
     * Method testCreateUserSuccessfully.
     *
     * Tests that the command creates a user successfully.
     *
     * @return void No return value
     */
    public function testCreateUserSuccessfully(): void
    {
        $command = $this->application?->find('app:user:create');
        $this->assertNotNull($command);

        $commandTester = new CommandTester($command);

        $email = 'test-' . uniqid() . '@example.com';

        $commandTester->execute([
            'email' => $email,
            'password' => 'TestPassword123!',
            '--username' => 'testuser' . uniqid(),
            '--first-name' => 'Test',
            '--last-name' => 'User',
        ]);

        $output = $commandTester->getDisplay();
        $exitCode = $commandTester->getStatusCode();

        // Debug output
        if (0 !== $exitCode) {
            $this->fail("Command failed with exit code {$exitCode}. Output: {$output}");
        }

        $this->assertStringContainsString('successfully', $output);
    }

    /**
     * Method testHandlerDirectly.
     *
     * Tests the handler directly via messenger bus.
     *
     * @return void No return value
     */
    public function testHandlerDirectly(): void
    {
        $container = self::getContainer();

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $command = new CreateUserCommand(
            username: 'directtest' . uniqid(),
            email: 'direct-' . uniqid() . '@example.com',
            password: 'TestPassword123!',
            firstName: 'Direct',
            lastName: 'Test',
        );

        try {
            $envelope = $bus->dispatch($command);

            $handledStamp = $envelope->last(HandledStamp::class);
            $this->assertNotNull($handledStamp, 'Handler should return a result');

            $result = $handledStamp->getResult();
            $this->assertNotNull($result, 'Result should not be null');
        } catch (Throwable $e) {
            $this->fail('Exception thrown: ' . get_class($e) . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
    // #endregion
}

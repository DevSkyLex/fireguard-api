<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\CreateUser\CreateUserCommand;
use User\Application\UseCase\Command\CreateUser\CreateUserResult;
use User\Presentation\Api\Dto\UserInput;
use User\Presentation\Api\Dto\UserOutput;

/**
 * Processor CreateUserProcessor
 * @final
 *
 * Processor for creating a new user.
 *
 * @category Processor
 * @package User\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UserInput, UserOutput>
 */
final readonly class CreateUserProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * CreateUserProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   *
   * Processes the user creation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return UserOutput The created user output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
  {
    /** @var UserInput $input */
    $input = $data;

    $command = new CreateUserCommand(
      username: $input->username ?? '',
      email: $input->email ?? '',
      password: $input->password ?? '',
      firstName: $input->firstName ?? '',
      lastName: $input->lastName ?? '',
      avatarUrl: $input->avatarUrl,
      tenantId: $input->tenantId
    );

    /** @var CreateUserResult $result */
    $result = $this->commandBus->dispatch(command: $command);

    $output = new UserOutput();
    $output->id = $result->userId;
    $output->username = $input->username;
    $output->email = $input->email;
    $output->firstName = $input->firstName;
    $output->lastName = $input->lastName;
    $output->avatarUrl = $input->avatarUrl;
    // Status and other fields will be set by default values or logic

    return $output;
  }
  //#endregion
}

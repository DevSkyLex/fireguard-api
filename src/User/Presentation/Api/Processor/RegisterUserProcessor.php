<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\RegisterUser\RegisterUserCommand;
use User\Application\UseCase\Command\RegisterUser\RegisterUserResult;
use User\Presentation\Api\Resource\UserResource;

/**
 * Processor RegisterUserProcessor
 * @final
 *
 * Processes user registration requests.
 *
 * @category Processor
 * @package User\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UserResource, UserResource>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
  /**
   * Constructor
   * 
   * Initializes a new instance of the RegisterUserProcessor class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private CommandBusPort $commandBus,
  ) {
  }

  /**
   * Method process
   *
   * Processes the user registration request.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param mixed $data The data to process (UserResource).
   * @param Operation $operation The operation being performed.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   * 
   * @return UserResource The processed data.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
  {
    $command = new RegisterUserCommand(
      username: $data->username,
      email: $data->email,
      password: $data->password,
      firstName: $data->firstName,
      lastName: $data->lastName,
      tenantId: $data->tenantId,
    );

    /** @var RegisterUserResult $result */
    $result = $this->commandBus->dispatch($command);

    // Update the resource with the new ID
    $data->id = $result->userId;

    return $data;
  }
}

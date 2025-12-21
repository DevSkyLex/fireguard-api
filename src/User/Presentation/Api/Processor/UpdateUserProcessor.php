<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\UseCase\Command\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;
use User\Presentation\Api\Dto\UserInput;
use User\Presentation\Api\Dto\UserOutput;

use function is_string;

/**
 * Processor UpdateUserProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UserInput, UserOutput|null>
 */
final readonly class UpdateUserProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateUserProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the update user request.
   *
   * @since 1.0.0
   *
   * @param mixed                $data         the input data
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return UserOutput|null the output data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?UserOutput
  {
    if (!$data instanceof UserInput) {
      return null;
    }

    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      return null;
    }

    $command = new UpdateUserCommand(
      id: $id,
      firstName: $data->firstName,
      lastName: $data->lastName,
      avatarUrl: $data->avatarUrl
    );

    $this->commandBus->dispatch(command: $command);

    // Fetch the updated user
    $query = new GetUserQuery(id: $id);
    /** @var GetUserResult $result */
    $result = $this->queryBus->ask(query: $query);
    $user = $result->user;

    if (!$user) {
      return null;
    }

    $output = new UserOutput();
    $output->id = $user->id()->value;
    $output->username = $user->username()->value;
    $output->email = $user->email()->value;
    $output->firstName = $user->profile()->firstName;
    $output->lastName = $user->profile()->lastName;
    $output->avatarUrl = $user->profile()->avatarUrl;
    $output->status = $user->status()->value;
    $output->emailVerified = $user->isEmailVerified();
    $output->tenantId = $user->tenantId()?->__toString();
    $output->createdAt = $user->createdAt()->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $user->lastLoginAt()?->format(DateTimeInterface::ATOM);

    return $output;
  }
}

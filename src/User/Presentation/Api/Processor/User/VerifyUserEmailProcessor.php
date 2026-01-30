<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\VerifyUserEmail\VerifyUserEmailCommand;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Presentation\Api\Dto\Output\User\UserOutput;

use function is_string;

/**
 * Processor VerifyUserEmailProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, UserOutput|null>
 */
final readonly class VerifyUserEmailProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * VerifyUserEmailProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
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
   * Processes the verify email request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return UserOutput|null the output data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?UserOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      return null;
    }

    $this->commandBus->dispatch(
      command: new VerifyUserEmailCommand(id: $id),
    );

    $query = new GetUserQuery(id: $id);
    /** @var GetUserResult $result */
    $result = $this->queryBus->ask(query: $query);
    $user = $result->user;

    if (!$user instanceof UserView) {
      return null;
    }

    $output = new UserOutput();
    $output->id = $user->id;
    $output->username = $user->username;
    $output->email = $user->email;
    $output->firstName = $user->firstName;
    $output->lastName = $user->lastName;
    $output->avatarUrl = $user->avatarUrl;
    $output->status = $user->status;
    $output->emailVerified = $user->emailVerified;
    $output->tenantId = $user->tenantId;
    $output->createdAt = $user->createdAt->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $user->lastLoginAt?->format(DateTimeInterface::ATOM);

    return $output;
  }
  // #endregion
}

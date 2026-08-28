<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\EmailChange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\UseCase\Command\EmailChange\CancelEmailChange\CancelEmailChangeCommand;

/**
 * Processor CancelEmailChangeProcessor.
 *
 * Cancels the authenticated user's pending email change request.
 * Idempotent: answers 204 whether or not a request was pending.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class CancelEmailChangeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CancelEmailChangeProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the cancellation for the authenticated user.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (none)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @throws AccessDeniedHttpException when not authenticated
   *
   * @return void No content (204)
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $this->commandBus->dispatch(new CancelEmailChangeCommand(
      userId: $user->getId(),
    ));
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Command\Organization\LeaveOrganization\LeaveOrganizationCommand;
use Organization\Domain\Exception\{
  OrganizationLastAdminException,
  OrganizationMemberNotFoundException,
  OrganizationNotFoundException,
  OrganizationOwnerCannotLeaveException
};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function is_string;

/**
 * Processor LeaveOrganizationProcessor.
 *
 * Lets the authenticated user deactivate their own membership
 * (self-removal). No permission check is performed here beyond
 * authentication: leaving is a self-service action gated only on the
 * caller's own active membership, the owner-cannot-leave guard and the
 * last-administrator lockout guard, all enforced by
 * `LeaveOrganizationHandler`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class LeaveOrganizationProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the LeaveOrganizationProcessor class.
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
   * Authorizes and dispatches the leave-organization command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    try {
      $this->commandBus->dispatch(new LeaveOrganizationCommand(
        organizationId: $organizationId,
        actingUserId: $user->getId(),
      ));
    } catch (OrganizationOwnerCannotLeaveException|OrganizationLastAdminException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $this->rethrowDomainFailure($exception);

      throw $exception;
    }

    return null;
  }

  /**
   * Method rethrowDomainFailure.
   *
   * Unwraps a messenger runtime failure and rethrows the matching HTTP error.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught runtime exception
   */
  private function rethrowDomainFailure(Throwable $exception): void
  {
    $current = $exception;

    while (null !== $current) {
      foreach ($this->wrappedExceptions($current) as $candidate) {
        if ($candidate instanceof OrganizationOwnerCannotLeaveException || $candidate instanceof OrganizationLastAdminException) {
          throw new ConflictHttpException($candidate->getMessage(), $exception);
        }

        if ($candidate instanceof OrganizationNotFoundException || $candidate instanceof OrganizationMemberNotFoundException) {
          throw new NotFoundHttpException($candidate->getMessage(), $exception);
        }
      }

      $current = $current->getPrevious();
    }
  }

  /**
   * Method wrappedExceptions.
   *
   * Yields the exception itself and any handler-wrapped exceptions.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to expand
   *
   * @return iterable<Throwable> the candidate exceptions
   */
  private function wrappedExceptions(Throwable $exception): iterable
  {
    yield $exception;

    if ($exception instanceof HandlerFailedException) {
      yield from $exception->getWrappedExceptions();
    }
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember\{ReactivateOrganizationMemberCommand, ReactivateOrganizationMemberResult};
use Organization\Domain\Exception\{
  OrganizationArchivedException,
  OrganizationMemberNotFoundException,
  OrganizationMemberNotInactiveException,
  OrganizationNotFoundException,
  OrganizationQuotaExceededException
};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor ReactivateOrganizationMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, OrganizationMemberOutput>
 */
final readonly class ReactivateOrganizationMemberProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The command bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerCommandBusAdapter::dispatch()`),
   * so a direct `catch (OrganizationNotFoundException|…)` around
   * `commandBus->dispatch()` alone would never match at runtime — this trait
   * walks the wrapped exception's `getPrevious()`/`HandlerFailedException`
   * chain to find the real domain exception underneath. See
   * `DeleteOrganizationProcessor` and `GetOrganizationNavigationCountersProvider`
   * for the same pattern.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ReactivateOrganizationMemberProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the reactivate-member request and dispatches the corresponding
   * command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationMemberOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $memberId = $uriVariables['memberId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($memberId) || '' === $memberId) {
      throw new BadRequestHttpException('OrganizationId and memberId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing organization.members.manage permission.');
    }

    try {
      /** @var ReactivateOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new ReactivateOrganizationMemberCommand(
        organizationId: $organizationId,
        memberId: $memberId,
      ));
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationMemberNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      // Both the archived-organization guard and the "member is already
      // active" guard are conflicts with the current state, not client
      // input errors — 409. The member cap is enforced in the same handler
      // transaction as AddOrganizationMemberHandler's re-add path (see
      // ReactivateOrganizationMemberHandler), so its 409 arrives wrapped by
      // the bus exactly like theirs and must be unwrapped here too.
      $conflict = $this->findWrappedException($exception, OrganizationArchivedException::class)
        ?? $this->findWrappedException($exception, OrganizationMemberNotInactiveException::class)
        ?? $this->findWrappedException($exception, OrganizationQuotaExceededException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new OrganizationMemberOutput();
    $output->id = $result->memberId;
    $output->organizationId = $result->organizationId;
    $output->userId = $result->userId;
    $output->isActive = $result->isActive;
    $output->joinedAt = $result->joinedAt->format('c');
    $output->roleIds = $result->roleIds;

    return $output;
  }
  // #endregion
}

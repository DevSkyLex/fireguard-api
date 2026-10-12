<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use LogicException;
use Organization\Application\Port\Inbound\Internal\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers\{RemoveOrganizationMembersCommand, RemoveOrganizationMembersResult};
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Presentation\Api\Dto\Input\Organization\RemoveOrganizationMembersInput;
use Organization\Presentation\Api\Dto\Output\Organization\RemoveOrganizationMembersOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

use function is_string;

/**
 * Processor RemoveOrganizationMembersProcessor.
 *
 * Removes several members in one request by dispatching the single-member
 * command per id, isolating each so one failure never aborts the rest, and
 * reporting which ids were removed and which failed.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RemoveOrganizationMembersInput, RemoveOrganizationMembersOutput>
 */
final readonly class RemoveOrganizationMembersProcessor implements ProcessorInterface
{
  use UnwrapsOrganizationBusFailures;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationMembersProcessor class.
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
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the batch remove-member request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(
    mixed $data,
    Operation $operation,
    array $uriVariables = [],
    array $context = [],
  ): RemoveOrganizationMembersOutput {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$data instanceof RemoveOrganizationMembersInput) {
      throw new BadRequestHttpException('A list of member IDs is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing organization.members.manage permission.');
    }

    // Advisory pre-check: refuse a doomed batch upfront, with a message naming the
    // real reason, rather than letting the caller watch every id fail one by one.
    // The primary batch repeats this check under the organization lock in its
    // owning transaction. A later invariant refusal rolls back the batch and is
    // translated into a conflict below.
    try {
      $this->lastAdminGuard->assertCanRemoveMembers($organizationId, $data->memberIds);
    } catch (OrganizationLastAdminException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }

    try {
      $result = $this->commandBus->dispatch(new RemoveOrganizationMembersCommand($organizationId, $data->memberIds));
    } catch (MessengerRuntimeException $exception) {
      // A lockout refusal is not a per-id failure to be tallied: the batch is
      // stripping the organization's last administrator and the caller must be
      // told so with a 409. Swallowing it into failedIds would report a partial
      // success and hide the invariant that stopped it.
      $lastAdmin = $this->findWrappedException($exception, OrganizationLastAdminException::class);
      if (null !== $lastAdmin) {
        throw new ConflictHttpException($lastAdmin->getMessage(), $exception);
      }

      throw $exception;
    }
    if (!$result instanceof RemoveOrganizationMembersResult) {
      throw new LogicException('Unexpected batch removal result.');
    }
    $output = new RemoveOrganizationMembersOutput();
    $output->removedIds = $result->removedIds;
    $output->failedIds = $result->failedIds;

    return $output;
  }
  // #endregion
}

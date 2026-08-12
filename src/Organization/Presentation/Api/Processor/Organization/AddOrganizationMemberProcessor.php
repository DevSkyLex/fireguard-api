<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException, OrganizationQuotaExceededException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\AddOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor AddOrganizationMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AddOrganizationMemberInput, OrganizationMemberOutput>
 */
final readonly class AddOrganizationMemberProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The direct `catch` clauses below cover the grant guard, which runs
   * in-process before dispatch and therefore throws bare. Everything the
   * handler throws arrives wrapped in `MessengerRuntimeException` instead,
   * and the clause using this trait is what maps it.
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
   * AddOrganizationMemberProcessor class.
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
    private OrganizationPermissionGrantGuardPort $grantGuard,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method process.
   *
   * Processes API input and dispatches the corresponding command.
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
    /** @var AddOrganizationMemberInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing Organization.members.manage permission.');
    }

    try {
      // Privilege-escalation guard: reject assigning roles that carry
      // permissions the caller does not hold (prevents self-granting an
      // owner-tier role in a single add-member request).
      $this->grantGuard->assertCanAssignRoles($user->getId(), $organizationId, $data->roleIds);

      /** @var AddOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new AddOrganizationMemberCommand(
        organizationId: $organizationId,
        userId: $data->userId,
        roleIds: $data->roleIds,
      ));
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException|OrganizationRoleNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      // Everything the handler throws arrives wrapped by the bus, so each
      // mapping declared above has to be recovered here as well.
      $quotaExceeded = $this->findWrappedException($exception, OrganizationQuotaExceededException::class);
      if (null !== $quotaExceeded) {
        throw new ConflictHttpException($quotaExceeded->getMessage(), $exception);
      }

      $accessDenied = $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
      if (null !== $accessDenied) {
        throw new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationRoleNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
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

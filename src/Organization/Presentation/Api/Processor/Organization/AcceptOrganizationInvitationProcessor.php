<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation\{AcceptOrganizationInvitationCommand, AcceptOrganizationInvitationResult};
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationNotFoundException, OrganizationQuotaExceededException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\AcceptOrganizationInvitationInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

/**
 * Processor AcceptOrganizationInvitationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AcceptOrganizationInvitationInput, OrganizationMemberOutput>
 */
final readonly class AcceptOrganizationInvitationProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AcceptOrganizationInvitationProcessor class.
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
    /** @var AcceptOrganizationInvitationInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      /** @var AcceptOrganizationInvitationResult $result */
      $result = $this->commandBus->dispatch(new AcceptOrganizationInvitationCommand(
        token: $data->token,
        userId: $user->getId(),
        userEmail: $user->getUserIdentifier(),
      ));
    } catch (OrganizationInvitationNotFoundException|OrganizationNotFoundException|OrganizationRoleNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      // The handler runs on the command bus, so its exceptions arrive wrapped and
      // must be unwrapped here: the member cap (assertCanAcceptMember) maps to 409,
      // and the domain not-found / validation failures to 404 / 400 (the direct
      // catches above never fire for bus-dispatched errors).
      $quotaExceeded = $this->findException($exception, OrganizationQuotaExceededException::class);
      if ($quotaExceeded instanceof OrganizationQuotaExceededException) {
        throw new ConflictHttpException($quotaExceeded->getMessage(), $exception);
      }

      $notFound = $this->findException($exception, OrganizationInvitationNotFoundException::class)
        ?? $this->findException($exception, OrganizationNotFoundException::class)
        ?? $this->findException($exception, OrganizationRoleNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
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

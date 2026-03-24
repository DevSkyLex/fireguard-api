<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\AddOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

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
      /** @var AddOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new AddOrganizationMemberCommand(
        organizationId: $organizationId,
        userId: $data->userId,
        roleIds: $data->roleIds,
      ));
    } catch (OrganizationNotFoundException|OrganizationRoleNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
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

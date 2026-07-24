<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\RemoveTeamMember\RemoveTeamMemberCommand;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamMemberNotFoundException, TeamNotFoundException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor RemoveTeamMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class RemoveTeamMemberProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveTeamMemberProcessor class.
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
   * Processes the remove-team-member request and dispatches the
   * corresponding command.
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
    $teamId = $uriVariables['teamId'] ?? null;
    $memberId = $uriVariables['memberId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($teamId) || '' === $teamId
      || !is_string($memberId) || '' === $memberId) {
      throw new BadRequestHttpException('OrganizationId, teamId, and memberId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.teams.write')) {
      throw new AccessDeniedHttpException('Missing organization.teams.write permission.');
    }

    try {
      $this->commandBus->dispatch(new RemoveTeamMemberCommand(
        organizationId: $organizationId,
        teamId: $teamId,
        memberId: $memberId,
      ));
    } catch (TeamNotFoundException|OrganizationNotFoundException|TeamMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }

    return null;
  }
  // #endregion
}

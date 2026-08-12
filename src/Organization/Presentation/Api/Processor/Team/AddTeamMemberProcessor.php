<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\AddTeamMember\{AddTeamMemberCommand, AddTeamMemberResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException, TeamNotFoundException};
use Organization\Presentation\Api\Dto\Input\Team\AddTeamMemberInput;
use Organization\Presentation\Api\Dto\Output\Team\TeamMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor AddTeamMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AddTeamMemberInput, TeamMemberOutput>
 */
final readonly class AddTeamMemberProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The command bus adapter wraps every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses below only
   * cover a bare in-process throw. The `MessengerRuntimeException` clause is
   * what maps the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddTeamMemberProcessor class.
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeamMemberOutput
  {
    /** @var AddTeamMemberInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $teamId = $uriVariables['teamId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($teamId) || '' === $teamId) {
      throw new BadRequestHttpException('OrganizationId and teamId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.teams.write')) {
      throw new AccessDeniedHttpException('Missing organization.teams.write permission.');
    }

    try {
      /** @var AddTeamMemberResult $result */
      $result = $this->commandBus->dispatch(new AddTeamMemberCommand(
        organizationId: $organizationId,
        teamId: $teamId,
        memberId: $data->memberId,
        role: $data->role,
      ));
    } catch (TeamNotFoundException|OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, TeamNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationMemberNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new TeamMemberOutput();
    $output->memberId = $result->memberId;
    $output->role = $result->role;
    $output->addedAt = $result->addedAt->format('c');

    return $output;
  }
  // #endregion
}

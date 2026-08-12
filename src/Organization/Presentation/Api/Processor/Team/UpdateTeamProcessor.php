<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\UpdateTeam\UpdateTeamCommand;
use Organization\Application\UseCase\Query\Team\GetTeam\{GetTeamQuery, GetTeamResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNameAlreadyExistsException, TeamNotFoundException};
use Organization\Presentation\Api\Dto\Input\Team\UpdateTeamInput;
use Organization\Presentation\Api\Dto\Output\Team\TeamOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor UpdateTeamProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateTeamInput, TeamOutput>
 */
final readonly class UpdateTeamProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * Both the command and the query below run on a bus, and the adapters wrap
   * every handler failure into `MessengerRuntimeException` — so the direct
   * `catch` clauses only cover a bare in-process throw. The
   * `MessengerRuntimeException` clause is what maps the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateTeamProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TeamOutput
  {
    /** @var UpdateTeamInput $data */
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
      $this->commandBus->dispatch(new UpdateTeamCommand(
        organizationId: $organizationId,
        teamId: $teamId,
        name: $data->name,
        description: $data->description,
      ));

      /** @var GetTeamResult $result */
      $result = $this->queryBus->ask(new GetTeamQuery(
        organizationId: $organizationId,
        teamId: $teamId,
      ));
    } catch (TeamNameAlreadyExistsException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (TeamNotFoundException|OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $nameConflict = $this->findWrappedException($exception, TeamNameAlreadyExistsException::class);
      if (null !== $nameConflict) {
        throw new ConflictHttpException($nameConflict->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, TeamNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new TeamOutput();
    $output->id = $result->id;
    $output->organizationId = $result->organizationId;
    $output->name = $result->name;
    $output->description = $result->description;
    $output->memberCount = $result->memberCount;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}

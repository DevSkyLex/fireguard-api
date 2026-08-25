<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RestoreOrganization\RestoreOrganizationCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationStatus;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Organization\Presentation\Api\Trait\OrganizationOutputMapperTrait;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor RestoreOrganizationProcessor.
 *
 * Restores an organization to ACTIVE (from SUSPENDED or ARCHIVED) as an
 * explicit, dedicated action, distinct from (and coexisting with) the
 * legacy `isActive: true` toggle on `PATCH /organizations/{id}` — see
 * MODULE.md. Gated by `organization.settings.write`, the SAME permission
 * the legacy toggle already requires — mirroring `SuspendOrganizationProcessor`'s
 * choice so both directions of the transition agree.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, OrganizationOutput>
 */
final readonly class RestoreOrganizationProcessor implements ProcessorInterface
{
  /**
   * Trait OrganizationOutputMapperTrait.
   *
   * @see OrganizationOutputMapperTrait
   */
  use OrganizationOutputMapperTrait;

  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The bus adapters wrap every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses only cover a
   * bare in-process throw. The `MessengerRuntimeException` clauses using
   * this trait are what map the real dispatch path.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RestoreOrganizationProcessor class.
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
   * Dispatches the restore command and returns the refreshed organization
   * output. Idempotent: a second call against an already-active
   * organization succeeds and returns 200 unchanged.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (unused — input: false)
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    $isPlatformAdmin = $this->security->isGranted('ROLE_ADMIN');

    if (
      !$isPlatformAdmin
      && !$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.settings.write')
    ) {
      throw new AccessDeniedHttpException('Missing organization.settings.write permission.');
    }

    // Archiving is the terminal state: reopening it is a platform action, not
    // a self-service one, however entitled the caller is inside the
    // organization. Suspension keeps its self-service path.
    //
    // Enforced here rather than by withholding `organization.settings.write`
    // from archived organizations, because that permission also gates suspend,
    // update-settings, remove-logo, transfer-ownership and reactivate-member —
    // five operations that already answer 409 naming the archived state, and
    // that a blanket denial would flatten into a 403.
    if (!$isPlatformAdmin && $this->isArchived($organizationId, $user->getId())) {
      throw new AccessDeniedHttpException(
        'This organization is archived; only a platform administrator can reopen it.',
      );
    }

    try {
      $this->commandBus->dispatch(new RestoreOrganizationCommand(
        organizationId: $organizationId,
        actingUserId: $user->getId(),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildOutput($organizationId, $user->getId());
  }

  /**
   * Method isArchived.
   *
   * Reads the organization's current status to decide whether reopening it is
   * reserved to a platform administrator.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   * @param string $callerUserId the acting user identifier
   *
   * @return bool true when the organization is archived
   */
  private function isArchived(string $organizationId, string $callerUserId): bool
  {
    try {
      /** @var GetOrganizationResult $result */
      $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId, callerUserId: $callerUserId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    return OrganizationStatus::ARCHIVED->value === $result->status;
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output, the same
   * `buildOutput()` pattern `TransferOrganizationOwnershipProcessor`/
   * `UpdateOrganizationSettingsProcessor` use for every other mutating
   * organization operation. Passing `$callerUserId` through resolves
   * `isOwner`/`roles` for the acting user via
   * `OrganizationCallerMembershipPort` (see `GetOrganizationHandler`).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $callerUserId the acting user identifier
   *
   * @return OrganizationOutput the refreshed organization output
   */
  private function buildOutput(string $organizationId, string $callerUserId): OrganizationOutput
  {
    try {
      /** @var GetOrganizationResult $result */
      $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId, callerUserId: $callerUserId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    return $this->buildOrganizationOutput($result);
  }

  // #endregion
}

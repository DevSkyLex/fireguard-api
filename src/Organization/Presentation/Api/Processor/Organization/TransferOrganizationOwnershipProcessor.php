<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership\{
  TransferOrganizationOwnershipCommand,
  TransferOrganizationOwnershipResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Presentation\Api\Dto\Input\Organization\TransferOrganizationOwnershipInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Trait\OrganizationOutputMapperTrait;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException
};

use function is_string;

/**
 * Processor TransferOrganizationOwnershipProcessor.
 *
 * Transfers ownership of an organization from its current owner to another
 * active member on behalf of the authenticated user. Ownership transfer is
 * intentionally gated on the organization's current-owner identity rather
 * than on RBAC permissions — that check, and the danger-zone slug
 * confirmation (mirroring `DELETE /organizations/{id}`), are enforced by
 * `TransferOrganizationOwnershipHandler`; this processor only forwards the
 * request and maps the resulting domain failure to HTTP.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TransferOrganizationOwnershipInput, OrganizationOutput>
 */
final readonly class TransferOrganizationOwnershipProcessor implements ProcessorInterface
{
  /**
   * Trait OrganizationOutputMapperTrait.
   *
   * @see OrganizationOutputMapperTrait
   */
  use OrganizationOutputMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TransferOrganizationOwnershipProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Dispatches the ownership transfer command and returns the refreshed
   * organization output.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    /** @var TransferOrganizationOwnershipInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    /** @var TransferOrganizationOwnershipResult $result */
    $result = $this->commandBus->dispatch(new TransferOrganizationOwnershipCommand(
      organizationId: $organizationId,
      actingUserId: $user->getId(),
      newOwnerUserId: $data->newOwnerUserId,
      slugConfirmation: $data->slug,
    ));

    return $this->buildOutput($result->organizationId, $user->getId());
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output. The transfer
   * result only carries the previous/new owner ids and the transfer
   * timestamp, so the refreshed organization is re-read the same way
   * `UpdateOrganizationSettingsProcessor`/`ChangeOrganizationPlanProcessor`
   * do for every other mutating organization operation.
   *
   * `$callerUserId` is deliberately the ACTING user — the previous owner —
   * not the new owner: `GetOrganizationHandler` resolves `isOwner` against
   * the organization's (now updated) `ownerUserId`, so this naturally
   * reports `isOwner: false` for the caller after a successful transfer,
   * without any special-casing here.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $callerUserId the acting (previous owner) user identifier
   *
   * @return OrganizationOutput the refreshed organization output
   */
  private function buildOutput(string $organizationId, string $callerUserId): OrganizationOutput
  {
    /** @var GetOrganizationResult $result */
    $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId, callerUserId: $callerUserId));

    return $this->buildOrganizationOutput($result);
  }

  // #endregion
}

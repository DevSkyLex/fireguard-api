<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{ListOrganizationInvitationsQuery, ListOrganizationInvitationsResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider ListOrganizationInvitationsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationInvitationOutput>
 */
final readonly class ListOrganizationInvitationsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListOrganizationInvitationsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the Symfony security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationInvitationOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return [];
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing Organization.members.manage permission.');
    }

    try {
      /** @var ListOrganizationInvitationsResult $result */
      $result = $this->queryBus->ask(new ListOrganizationInvitationsQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach ($result->invitations as $invitation) {
      $output = new OrganizationInvitationOutput();
      $output->id = $invitation->id;
      $output->organizationId = $invitation->organizationId;
      $output->email = $invitation->email;
      $output->status = $invitation->status;
      $output->invitedByUserId = $invitation->invitedByUserId;
      $output->acceptedByUserId = $invitation->acceptedByUserId;
      $output->revokedByUserId = $invitation->revokedByUserId;
      $output->expiresAt = $invitation->expiresAt->format('c');
      $output->createdAt = $invitation->createdAt->format('c');
      $output->updatedAt = $invitation->updatedAt->format('c');
      $output->acceptedAt = null !== $invitation->acceptedAt ? $invitation->acceptedAt->format('c') : null;
      $output->revokedAt = null !== $invitation->revokedAt ? $invitation->revokedAt->format('c') : null;
      $output->roleIds = $invitation->roleIds;
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}

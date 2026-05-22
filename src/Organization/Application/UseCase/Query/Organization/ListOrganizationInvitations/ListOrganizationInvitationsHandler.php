<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function count;

/**
 * UseCase ListOrganizationInvitationsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationInvitationsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationInvitationsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationInvitationRepositoryPort $invitationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Lists invitations of an organization.
   *
   * @since 1.0.0
   *
   * @param ListOrganizationInvitationsQuery $query the query payload
   *
   * @return PaginatedResult<GetOrganizationInvitationResult> the use case result
   */
  public function __invoke(ListOrganizationInvitationsQuery $query): PaginatedResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $invitations = $this->invitationRepository->findByOrganizationId($organizationId);
    $now = new DateTimeImmutable();
    $results = [];

    foreach ($invitations as $invitation) {
      if ($invitation->status()->isPending() && $invitation->isExpired($now)) {
        $invitation->expire($now);
        $this->invitationRepository->save($invitation);
      }

      $results[] = new GetOrganizationInvitationResult(
        id: (string) $invitation->id(),
        organizationId: (string) $invitation->organizationId(),
        email: (string) $invitation->email(),
        status: $invitation->status()->value,
        invitedByUserId: $invitation->invitedByUserId(),
        acceptedByUserId: $invitation->acceptedByUserId(),
        revokedByUserId: $invitation->revokedByUserId(),
        expiresAt: $invitation->expiresAt(),
        createdAt: $invitation->createdAt(),
        updatedAt: $invitation->updatedAt(),
        acceptedAt: $invitation->acceptedAt(),
        revokedAt: $invitation->revokedAt(),
        roleIds: $this->invitationRepository->findRoleIdsForInvitation($invitation->id()),
      );
    }

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}

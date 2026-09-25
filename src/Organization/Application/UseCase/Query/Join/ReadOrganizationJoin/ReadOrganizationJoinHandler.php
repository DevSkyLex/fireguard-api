<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationJoinAccessPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationJoinException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationJoinMode};
use Shared\Application\Message\QueryHandler;
use User\Application\Port\Inbound\EmailOwnershipPort;

use function array_column;
use function array_map;
use function count;
use function in_array;
use function strrpos;
use function strtolower;
use function substr;

/**
 * UseCase ReadOrganizationJoinHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadOrganizationJoinHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRepositoryPort $joins persistence
   * @param OrganizationJoinAccessPort $access access projection
   * @param OrganizationRepositoryPort $organizations organizations
   * @param OrganizationInvitationRepositoryPort $invitations invitations
   * @param OrganizationMemberRepositoryPort $members memberships
   * @param EmailOwnershipPort $emails server-side address proof
   */
  public function __construct(private OrganizationJoinRepositoryPort $joins, private OrganizationJoinAccessPort $access, private OrganizationRepositoryPort $organizations, private OrganizationInvitationRepositoryPort $invitations, private OrganizationMemberRepositoryPort $members, private EmailOwnershipPort $emails)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param ReadOrganizationJoinQuery $query authenticated query
   *
   * @return ReadOrganizationJoinResult projection
   */
  public function __invoke(ReadOrganizationJoinQuery $query): ReadOrganizationJoinResult
  {
    if (in_array($query->operation, ['policy', 'organization_requests'], true)) {
      return $this->readManagedOperation($query);
    }
    $requests = array_map(fn ($r) => $this->access->requestView($r, $query->userId), $this->joins->requests($query->userId));
    if ('requests' === $query->operation) {
      return new ReadOrganizationJoinResult(['member' => $requests, 'totalItems' => count($requests)]);
    }
    $proof = $this->emails->get($query->userId);
    $result = ['emailProofRequired' => !$proof->verified, 'invitations' => [], 'organizations' => [], 'requests' => $requests];
    if ($proof->verified) {
      $now = new DateTimeImmutable();
      $result['invitations'] = $this->invitationViews($proof->email, $now);
      $result['organizations'] = $this->organizationViews($proof->email, $now, $result['invitations'], $requests, $query->userId);
    }

    return new ReadOrganizationJoinResult($result);
  }

  private function readManagedOperation(ReadOrganizationJoinQuery $query): ReadOrganizationJoinResult
  {
    $organizationId = $query->organizationId ?? '';
    if ('policy' === $query->operation) {
      $this->access->assertManage($query->userId, $organizationId, true);

      return new ReadOrganizationJoinResult($this->access->policyView($organizationId));
    }

    $this->access->assertManage($query->userId, $organizationId);
    $requests = array_map(fn ($r) => $this->access->requestView($r, $query->userId, true), $this->joins->requests(null, $query->organizationId));

    return new ReadOrganizationJoinResult(['member' => $requests, 'totalItems' => count($requests), 'assignableRoles' => $this->access->assignableRoles($query->userId, $organizationId)]);
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function invitationViews(string $email, DateTimeImmutable $now): array
  {
    $invitations = [];
    foreach ($this->joins->invitationIds($email) as $id) {
      $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($id));
      if (null === $invitation || !$invitation->status()->isPending() || $invitation->isExpired($now)) {
        continue;
      }
      $org = $this->organizations->findById($invitation->organizationId());
      if (null === $org || !$org->status()->isActive()) {
        continue;
      }
      $invitations[] = ['id' => $id, 'organizationId' => (string) $org->id(), 'organizationName' => (string) $org->name(), 'expiresAt' => $invitation->expiresAt()->format('c')];
    }

    return $invitations;
  }

  /**
   * @param list<array<string, mixed>> $invitations
   * @param list<array<string, mixed>> $requests
   *
   * @return list<array<string, mixed>>
   */
  private function organizationViews(string $email, DateTimeImmutable $now, array $invitations, array $requests, string $userId): array
  {
    $organizations = [];
    $emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));
    foreach ($this->joins->domainsForName($emailDomain) as $domain) {
      $view = $this->organizationView($domain, $now, $invitations, $requests, $userId);
      if (null !== $view) {
        $organizations[] = $view;
      }
    }

    return $organizations;
  }

  /**
   * @param list<array<string, mixed>> $invitations
   * @param list<array<string, mixed>> $requests
   *
   * @return array<string, mixed>|null
   */
  private function organizationView(OrganizationDomain $domain, DateTimeImmutable $now, array $invitations, array $requests, string $userId): ?array
  {
    $organization = $this->eligibleOrganization($domain, $now, $invitations);
    if (null === $organization) {
      return null;
    }
    $policy = $this->joins->policy($domain->organizationId);
    $access = OrganizationJoinMode::INVITATION_ONLY === $policy->mode
      ? null
      : $this->joinAccess($domain, $organization, $policy, $requests, $userId);

    return null === $access ? null : ['id' => $domain->organizationId, 'name' => (string) $organization->name(), 'logoUrl' => $organization->logoUrl(), 'domain' => $domain->domain, 'roleLabel' => $access['roleLabel'], 'actions' => $access['actions']];
  }

  /**
   * @param list<array<string, mixed>> $invitations
   */
  private function eligibleOrganization(OrganizationDomain $domain, DateTimeImmutable $now, array $invitations): ?Organization
  {
    if (!$domain->isUsable($now)) {
      return null;
    }

    $organization = $this->organizations->findById(OrganizationId::fromString($domain->organizationId));

    return null !== $organization && $organization->status()->isActive() && !in_array($domain->organizationId, array_column($invitations, 'organizationId'), true)
      ? $organization
      : null;
  }

  /**
   * @param list<array<string, mixed>> $requests
   *
   * @return array{roleLabel: ?string, actions: list<string>}|null
   */
  private function joinAccess(OrganizationDomain $domain, Organization $organization, OrganizationAccessPolicy $policy, array $requests, string $userId): ?array
  {
    $member = $this->members->findByOrganizationAndUser($organization->id(), $userId);
    $actions = ['request'];
    $roleLabel = null;
    if (null !== $member && $member->isActive()) {
      $actions = ['open'];
    } elseif (OrganizationJoinMode::AUTOMATIC === $policy->mode && null === $member && null !== $policy->roleId) {
      try {
        $roleLabel = $this->access->assertEligibleRole($domain->organizationId, $policy->roleId);
        $actions = ['join'];
      } catch (OrganizationJoinException) {
        return null;
      }
    }
    foreach ($requests as $request) {
      if ($request['organizationId'] === $domain->organizationId && 'pending' === $request['status']) {
        $actions = ['view_request'];

        break;
      }
    }

    return ['roleLabel' => $roleLabel, 'actions' => $actions];
  }
}

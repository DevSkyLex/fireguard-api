<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationJoinAccessPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\OrganizationJoinException;
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
    if ('policy' === $query->operation) {
      $this->access->assertManage($query->userId, $query->organizationId ?? '', true);

      return new ReadOrganizationJoinResult($this->access->policyView($query->organizationId ?? ''));
    }
    if ('organization_requests' === $query->operation) {
      $this->access->assertManage($query->userId, $query->organizationId ?? '');
      $requests = array_map(fn ($r) => $this->access->requestView($r, $query->userId, true), $this->joins->requests(null, $query->organizationId));

      return new ReadOrganizationJoinResult(['member' => $requests, 'totalItems' => count($requests), 'assignableRoles' => $this->access->assignableRoles($query->userId, $query->organizationId ?? '')]);
    }
    $requests = array_map(fn ($r) => $this->access->requestView($r, $query->userId), $this->joins->requests($query->userId));
    if ('requests' === $query->operation) {
      return new ReadOrganizationJoinResult(['member' => $requests, 'totalItems' => count($requests)]);
    }
    $proof = $this->emails->get($query->userId);
    $result = ['emailProofRequired' => !$proof->verified, 'invitations' => [], 'organizations' => [], 'requests' => $requests];
    if (!$proof->verified) {
      return new ReadOrganizationJoinResult($result);
    }
    $now = new DateTimeImmutable();
    foreach ($this->joins->invitationIds($proof->email) as $id) {
      $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($id));
      if (null === $invitation || !$invitation->status()->isPending() || $invitation->isExpired($now)) {
        continue;
      }
      $org = $this->organizations->findById($invitation->organizationId());
      if (null === $org || !$org->status()->isActive()) {
        continue;
      }
      $result['invitations'][] = ['id' => $id, 'organizationId' => (string) $org->id(), 'organizationName' => (string) $org->name(), 'expiresAt' => $invitation->expiresAt()->format('c')];
    }
    $emailDomain = strtolower(substr($proof->email, strrpos($proof->email, '@') + 1));
    foreach ($this->joins->domainsForName($emailDomain) as $domain) {
      if (!$domain->isUsable($now)) {
        continue;
      }
      $org = $this->organizations->findById(OrganizationId::fromString($domain->organizationId));
      if (null === $org || !$org->status()->isActive() || in_array($domain->organizationId, array_column($result['invitations'], 'organizationId'), true)) {
        continue;
      }
      $policy = $this->joins->policy($domain->organizationId);
      if (OrganizationJoinMode::INVITATION_ONLY === $policy->mode) {
        continue;
      }
      $member = $this->members->findByOrganizationAndUser($org->id(), $query->userId);
      $actions = ['request'];
      $roleLabel = null;
      if (null !== $member && $member->isActive()) {
        $actions = ['open'];
      } elseif (OrganizationJoinMode::AUTOMATIC === $policy->mode && null === $member && null !== $policy->roleId) {
        try {
          $roleLabel = $this->access->assertEligibleRole($domain->organizationId, $policy->roleId);
          $actions = ['join'];
        } catch (OrganizationJoinException) {
          continue;
        }
      }
      foreach ($requests as $request) {
        if ($request['organizationId'] === $domain->organizationId && 'pending' === $request['status']) {
          $actions = ['view_request'];

          break;
        }
      }
      $result['organizations'][] = ['id' => $domain->organizationId, 'name' => (string) $org->name(), 'logoUrl' => $org->logoUrl(), 'domain' => $domain->domain, 'roleLabel' => $roleLabel, 'actions' => $actions];
    }

    return new ReadOrganizationJoinResult($result);
  }
}

<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationJoinAccessPort};
use Organization\Application\Port\Outbound\{OrganizationJoinRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationJoinException, OrganizationNotFoundException};
use Organization\Domain\Model\OrganizationJoin\{OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode, OrganizationJoinPermissions, OrganizationRoleId, OrganizationRoleName};
use Throwable;
use User\Application\Port\Inbound\EmailOwnershipPort;

use function array_map;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Service OrganizationJoinAccessService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinAccessService implements OrganizationJoinAccessPort
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRepositoryPort $joins join persistence
   * @param OrganizationRepositoryPort $organizations organizations
   * @param OrganizationRoleRepositoryPort $roles scoped roles
   * @param OrganizationAuthorizationPort $authorization caller permissions
   * @param EmailOwnershipPort $emails authoritative email proof
   */
  public function __construct(private OrganizationJoinRepositoryPort $joins, private OrganizationRepositoryPort $organizations, private OrganizationRoleRepositoryPort $roles, private OrganizationAuthorizationPort $authorization, private EmailOwnershipPort $emails)
  {
  }

  public function assignableRoles(string $actor, string $organizationId): array
  {
    $this->assertManage($actor, $organizationId);
    $allowed = [];
    foreach ($this->roles->findByOrganizationId(OrganizationId::fromString($organizationId)) as $role) {
      $grantable = true;
      foreach ($role->permissions() as $permission) {
        if (!$this->authorization->hasPermission($actor, $organizationId, $permission)) {
          $grantable = false;

          break;
        }
      }
      if ($grantable) {
        $allowed[] = ['id' => (string) $role->id(), 'label' => (string) $role->name()];
      }
    }

    return $allowed;
  }

  public function assertManage(string $actor, string $organizationId, bool $settings = false): void
  {
    if (!$this->authorization->isMemberOf($actor, $organizationId)) {
      throw OrganizationNotFoundException::withId($organizationId);
    }
    $this->authorization->assertGrantedPermissions($actor, $organizationId, $settings ? ['organization.members.manage', 'organization.settings.write'] : ['organization.members.manage']);
    $org = $this->organizations->findById(OrganizationId::fromString($organizationId));
    if (null === $org || !$org->status()->isActive()) {
      throw new OrganizationJoinException('organization_join_unavailable');
    }
  }

  public function assertEligibleRole(string $organizationId, string $roleId): string
  {
    $role = $this->roles->findById(OrganizationRoleId::fromString($roleId));
    $member = $this->roles->findByOrganizationAndName(OrganizationId::fromString($organizationId), new OrganizationRoleName('member'));
    if (null === $role || null === $member || (string) $role->organizationId() !== $organizationId || !OrganizationJoinPermissions::isSubset($role->permissions(), $member->permissions())) {
      throw new OrganizationJoinException('organization_join_role_ineligible');
    }

    return (string) $role->name();
  }

  public function eligibleDomain(string $organizationId, string $email, DateTimeImmutable $now): OrganizationDomain
  {
    $domain = strtolower(substr($email, strrpos($email, '@') + 1));
    foreach ($this->joins->domains($organizationId) as $proof) {
      if ($proof->domain === $domain && $proof->isUsable($now)) {
        return $proof;
      }
    }

    throw new OrganizationJoinException('organization_join_domain_unavailable');
  }

  public function policyView(string $organizationId): array
  {
    $policy = $this->joins->policy($organizationId);
    $roleLabel = null;
    $eligible = [];
    $member = $this->roles->findByOrganizationAndName(OrganizationId::fromString($organizationId), new OrganizationRoleName('member'));
    $proposedRoleId = $policy->roleId ?? (null !== $member ? (string) $member->id() : null);
    foreach ($this->roles->findByOrganizationId(OrganizationId::fromString($organizationId)) as $role) {
      if (null !== $member && OrganizationJoinPermissions::isSubset($role->permissions(), $member->permissions())) {
        $eligible[] = ['id' => (string) $role->id(), 'label' => (string) $role->name()];
        if ((string) $role->id() === $proposedRoleId) {
          $roleLabel = (string) $role->name();
        }
      }
    }

    return ['mode' => $policy->mode->value, 'roleId' => $proposedRoleId, 'roleLabel' => $roleLabel, 'domains' => array_map($this->domainView(...), $this->joins->domains($organizationId)), 'eligibleRoles' => $eligible];
  }

  public function domainView(OrganizationDomain $domain): array
  {
    return ['id' => $domain->id, 'domain' => $domain->domain, 'status' => 'verified' === $domain->status && !$domain->isUsable(new DateTimeImmutable()) ? 'suspended' : $domain->status, 'dnsName' => $domain->dnsName(), 'dnsValue' => $domain->dnsValue, 'verifiedAt' => $domain->verifiedAt?->format('c'), 'lastCheckedAt' => $domain->lastCheckedAt?->format('c')];
  }

  public function requestView(OrganizationJoinRequest $request, string $actor, bool $manager = false): array
  {
    $org = $this->organizations->findById(OrganizationId::fromString($request->organizationId));
    $state = $request->state(new DateTimeImmutable());

    try {
      $email = $this->emails->get($request->userId);
      $emailMatches = $email->verified && null !== $email->verifiedAt && $email->verifiedAt <= $request->createdAt && strtolower($email->email) === $request->email;
    } catch (Throwable $failure) {
      if ('email_ownership_unavailable' !== $failure->getMessage()) {
        throw $failure;
      }
      $emailMatches = false;
    }
    if ('pending' === $state && !$emailMatches) {
      $state = 'cancelled';
    }
    $actions = 'pending' === $state ? ($actor === $request->userId ? ['cancel'] : []) : [];
    if ($manager && 'pending' === $state) {
      $actions = ['reject'];
      if (null !== $org && $org->status()->isActive() && isset($email) && $email->verified && OrganizationJoinMode::INVITATION_ONLY !== $this->joins->policy($request->organizationId)->mode) {
        try {
          $this->eligibleDomain($request->organizationId, $email->email, new DateTimeImmutable());
          $actions = ['approve', 'reject'];
        } catch (OrganizationJoinException) { /* Missing proof is an expected eligibility state. */
        }
      }
    }
    if ('approved' === $state && $actor === $request->userId && null !== $org && $org->status()->isActive() && $this->authorization->isMemberOf($actor, $request->organizationId)) {
      $actions = ['open'];
    }

    return [...($manager ? ['applicantEmail' => $request->email] : []), 'id' => $request->id, 'organizationId' => $request->organizationId, 'organizationName' => null !== $org ? (string) $org->name() : '', 'status' => $state, 'createdAt' => $request->createdAt->format('c'), 'expiresAt' => $request->expiresAt->format('c'), 'actions' => $actions];
  }

  public function assertRoleChange(string $organizationId, string $roleId, ?array $permissions): void
  {
    $this->joins->lock($organizationId);
    $policy = $this->joins->policy($organizationId);
    if (OrganizationJoinMode::AUTOMATIC !== $policy->mode || $roleId !== $policy->roleId) {
      return;
    }
    $member = $this->roles->findByOrganizationAndName(OrganizationId::fromString($organizationId), new OrganizationRoleName('member'));
    if (null === $permissions || null === $member || !OrganizationJoinPermissions::isSubset($permissions, $member->permissions())) {
      throw new OrganizationJoinException('organization_join_role_in_use');
    }
  }
}

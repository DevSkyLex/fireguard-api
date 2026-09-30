<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationJoinAccessPort};
use Organization\Application\Port\Outbound\{OrganizationJoinRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationJoinException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationJoin\{OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode, OrganizationJoinPermissions, OrganizationRoleId, OrganizationRoleName};
use Throwable;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;

use function array_map;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Service OrganizationJoinAccessService.
 *
 * @category Service
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

  /**
   * Method assignableRoles
   *
   * Lists organization roles whose permissions the actor may grant.
   *
   * @access public
   *
   * @param string $actor the actor identifier
   * @param string $organizationId the organization identifier
   *
   * @return list<array{id: string, label: string}> assignable role identifiers and labels
   */
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

  /**
   * Method assertManage
   *
   * Requires organization membership, management permissions and an active organization.
   *
   * @access public
   *
   * @param string $actor the actor identifier
   * @param string $organizationId the organization identifier
   * @param bool $settings whether settings-write permission is also required
   *
   * @return void
   *
   * @throws OrganizationNotFoundException when the actor is not a member
   * @throws OrganizationJoinException when the organization is unavailable
   */
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

  /**
   * Method assertEligibleRole
   *
   * Validates that the selected organization role does not exceed the member role.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier
   *
   * @return string the validated role name
   *
   * @throws OrganizationJoinException when the role is not eligible
   */
  public function assertEligibleRole(string $organizationId, string $roleId): string
  {
    $role = $this->roles->findById(OrganizationRoleId::fromString($roleId));
    $member = $this->roles->findByOrganizationAndName(OrganizationId::fromString($organizationId), new OrganizationRoleName('member'));
    if (null === $role || null === $member || (string) $role->organizationId() !== $organizationId || !OrganizationJoinPermissions::isSubset($role->permissions(), $member->permissions())) {
      throw new OrganizationJoinException('organization_join_role_ineligible');
    }

    return (string) $role->name();
  }

  /**
   * Method eligibleDomain
   *
   * Finds a usable verified domain matching the email address.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $email the applicant email address
   * @param DateTimeImmutable $now time used to check domain usability
   *
   * @return OrganizationDomain the matching usable domain
   *
   * @throws OrganizationJoinException when no usable matching domain exists
   */
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

  /**
   * Method policyView
   *
   * Builds the join policy view with eligible roles and verified domains.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, mixed> policy data with verified domains and eligible role choices
   */
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

  /**
   * Method domainView
   *
   * Converts a domain model to its API-facing values.
   *
   * @access public
   *
   * @param OrganizationDomain $domain the organization domain
   *
   * @return array<string, mixed> domain values for the API view
   */
  public function domainView(OrganizationDomain $domain): array
  {
    return ['id' => $domain->id, 'domain' => $domain->domain, 'status' => 'verified' === $domain->status && !$domain->isUsable(new DateTimeImmutable()) ? 'suspended' : $domain->status, 'dnsName' => $domain->dnsName(), 'dnsValue' => $domain->dnsValue, 'verifiedAt' => $domain->verifiedAt?->format('c'), 'lastCheckedAt' => $domain->lastCheckedAt?->format('c')];
  }

  /**
   * Method requestView
   *
   * Builds a join request view and the actions available to the actor.
   *
   * @access public
   *
   * @param OrganizationJoinRequest $request the join request
   * @param string $actor the actor identifier
   * @param bool $manager whether the actor manages organization membership
   *
   * @return array<string, mixed> request values and actor-specific actions
   */
  public function requestView(OrganizationJoinRequest $request, string $actor, bool $manager = false): array
  {
    $org = $this->organizations->findById(OrganizationId::fromString($request->organizationId));
    $state = $request->state(new DateTimeImmutable());
    $email = $this->requestEmailProof($request);
    if ('pending' === $state && !$this->emailMatchesRequest($email, $request)) {
      $state = 'cancelled';
    }
    $actions = $this->requestActions($request, $actor, $manager, $state, $org, $email);

    return [...($manager ? ['applicantEmail' => $request->email] : []), 'id' => $request->id, 'organizationId' => $request->organizationId, 'organizationName' => null !== $org ? (string) $org->name() : '', 'status' => $state, 'createdAt' => $request->createdAt->format('c'), 'expiresAt' => $request->expiresAt->format('c'), 'actions' => $actions];
  }

  /**
   * Method assertRoleChange
   *
   * Prevents changing the automatic join role to permissions beyond the member role.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier
   * @param list<string>|null $permissions proposed permissions, when supplied
   *
   * @return void
   *
   * @throws OrganizationJoinException when the role remains in use but becomes ineligible
   */
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

  /**
   * Method requestEmailProof
   *
   * Reads the applicant's authoritative email ownership result.
   *
   * @access private
   *
   * @param OrganizationJoinRequest $request the join request
   *
   * @return ?EmailOwnershipResult the proof, or null when the ownership service is unavailable
   */
  private function requestEmailProof(OrganizationJoinRequest $request): ?EmailOwnershipResult
  {
    try {
      return $this->emails->get($request->userId);
    } catch (Throwable $failure) {
      if ('email_ownership_unavailable' !== $failure->getMessage()) {
        throw $failure;
      }

      return null;
    }
  }

  /**
   * Method emailMatchesRequest
   *
   * Checks that verified email ownership predates and matches the request address.
   *
   * @access private
   *
   * @param ?EmailOwnershipResult $email the current email ownership result
   * @param OrganizationJoinRequest $request the join request
   *
   * @return bool whether the proof matches the request
   */
  private function emailMatchesRequest(?EmailOwnershipResult $email, OrganizationJoinRequest $request): bool
  {
    return null !== $email
      && $email->verified
      && null !== $email->verifiedAt
      && $email->verifiedAt <= $request->createdAt
      && strtolower($email->email) === $request->email;
  }

  /**
   * @return list<string>
   */
  private function requestActions(
    OrganizationJoinRequest $request,
    string $actor,
    bool $manager,
    string $state,
    ?Organization $org,
    ?EmailOwnershipResult $email,
  ): array {
    $actions = [];
    if ('pending' === $state && $actor === $request->userId) {
      $actions = ['cancel'];
    }
    if ($manager && 'pending' === $state) {
      $actions = ['reject'];
      if (null !== $org && $org->status()->isActive() && null !== $email && $email->verified && OrganizationJoinMode::INVITATION_ONLY !== $this->joins->policy($request->organizationId)->mode) {
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

    return $actions;
  }
}

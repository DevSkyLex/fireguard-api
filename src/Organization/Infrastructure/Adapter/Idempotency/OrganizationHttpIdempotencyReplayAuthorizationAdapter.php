<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Contract\Exception\OrganizationNotFoundException;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Organization\Infrastructure\Service\Idempotency\OrganizationReplayInput;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use User\Application\Port\Inbound\FreshEmailOwnershipPort;

use function hash;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;

/**
 * Class OrganizationHttpIdempotencyReplayAuthorizationAdapter
 *
 * Resolves only owner tables, preserves signed deleted-target scope, and rechecks current member, manager or applicant authority before opening a receipt.
 *
 * @category Adapter
 */
final readonly class OrganizationHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constants
  /**
   * @var array<string,string> explicit owner scope lookup tables
   */
  private const array TABLES = [
    'organization_member' => 'organization_members',
    'organization_role' => 'organization_roles',
    'organization_invitation' => 'organization_invitations',
    'organization_domain' => 'organization_domains',
    'organization_join_request' => 'organization_join_requests',
    'team' => 'teams',
  ];
  // #endregion

  // #region Constructor
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer fresh account and original captured authority
   * @param Connection $connection explicitly selected main connection
   * @param FreshOrganizationAuthorizationPort $organizations current uncached organization authority
   * @param HttpIdempotencyAccountPort $accounts current owner account facts
   * @param HttpIdempotencyPlatformAuthorityPort $platform current native platform restore authority
   * @param FreshEmailOwnershipPort $emails current verified applicant identity
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection, private FreshOrganizationAuthorizationPort $organizations, private HttpIdempotencyAccountPort $accounts, private HttpIdempotencyPlatformAuthorityPort $platform, private FreshEmailOwnershipPort $emails)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (OrganizationHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $operationName) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertSupported($request);

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $name = $request->operation->name;
    $kind = $request->operation->resourceKind;
    $organizationId = OrganizationReplayInput::identifier($request->uriVariables['organizationId'] ?? null);
    if ('plan' === $kind) {
      return $this->authorizePlan($request, $storedProof);
    }
    if (null === $organizationId && 'organization' === $kind) {
      $organizationId = OrganizationReplayInput::identifier($request->uriVariables['id'] ?? $storedProof?->metadata['organizationId'] ?? null);
    }
    [$organizationId, $scopeFacts] = $this->routeScopes($request, $storedProof, $organizationId);
    $organizationId = $this->storedResourceScope($request, $storedProof, $organizationId);
    $fields = OrganizationReplayInput::fields($request);
    [$organizationId, $scopeFacts] = $this->tokenInvitationScope($request, $fields, $organizationId, $scopeFacts);
    $this->currentOrganizationScope($request, $storedProof, $organizationId, $fields);
    $privateRequest = in_array($name, ['organization_join_request', 'organization_join_cancel_request'], true);
    if ($privateRequest) {
      $this->assertApplicant($request, $storedProof, $organizationId);
    }
    $this->assertInvitationRecipient($request);
    $platformRestore = 'restoreOrganization' === $name && $this->platformRestore($request);
    if (null !== $storedProof && '1' === ($storedProof->metadata['platformRestore'] ?? null) && !$platformRestore) {
      $this->deny();
    }
    $this->assertCurrentAuthority($request, $storedProof, $organizationId, $platformRestore, $privateRequest);
    $this->assertScopeFacts($storedProof, $scopeFacts);
    $proof = $this->authorizer->authorize($request, $storedProof, $organizationId);

    if (null === $storedProof) {
      $proof = [...$proof, ...$scopeFacts, 'platformRestore' => $platformRestore ? '1' : '0'];
    }

    return $proof;
  }

  /**
   * @param string $name exact owner operation
   *
   * @return list<string> current required owner permissions
   */
  private function permissions(string $name): array
  {
    if (in_array($name, ['organization_join_update_access_policy', 'organization_join_add_domain', 'organization_join_verify_domain', 'organization_join_remove_domain'], true)) {
      return ['organization.members.manage', 'organization.settings.write'];
    }
    if (in_array($name, ['organization_join_approve_request', 'organization_join_reject_request'], true)) {
      return ['organization.members.manage'];
    }

    return match ($name) {
      'listOrganizationInvitations', 'inviteOrganizationMember', 'revokeOrganizationInvitation', 'resendOrganizationInvitation', 'addOrganizationMember', 'batchRemoveOrganizationMembers', 'reactivateOrganizationMember', 'removeOrganizationMember' => ['organization.members.manage'],
      'createOrganizationRole', 'updateOrganizationRole', 'deleteOrganizationRole', 'assignOrganizationRoleToMember', 'setOrganizationMemberRoles', 'removeOrganizationRoleFromMember' => ['organization.roles.manage'],
      'createTeam', 'updateTeam', 'addTeamMember', 'removeTeamMember' => ['organization.teams.write'],
      'deleteTeam' => ['organization.teams.manage'],
      'updateOrganizationSettings', 'changeOrganizationPlan', 'uploadOrganizationLogo', 'transferOrganizationOwnership', 'suspendOrganization', 'restoreOrganization', 'removeOrganizationLogo' => ['organization.settings.write'],
      'deleteOrganization' => ['organization.delete'],
      default => [],
    };
  }

  /**
   * @param array<string,mixed> $fields input scope references
   * @param string $organizationId exact route scope
   */
  private function payloadScopes(array $fields, string $organizationId): void
  {
    foreach (['memberId' => 'organization_member', 'roleId' => 'organization_role'] as $field => $kind) {
      $id = OrganizationReplayInput::identifier($fields[$field] ?? null);
      if (null !== $id && $this->targetOrganization($kind, $id) !== $organizationId) {
        throw OrganizationNotFoundException::withId($id);
      }
    }
    foreach (['roleIds' => 'organization_role', 'memberIds' => 'organization_member'] as $field => $kind) {
      if (!is_array($fields[$field] ?? null)) {
        continue;
      }
      foreach ($fields[$field] as $id) {
        if (is_string($id) && null !== ($scope = $this->targetOrganization($kind, $id)) && $scope !== $organizationId) {
          throw OrganizationNotFoundException::withId($id);
        }
      }
    }
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request current caller
   * @param IdempotencyProof|null $storedProof verified receipt
   * @param string|null $organizationId current owner scope
   */
  private function assertApplicant(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $organizationId): void
  {
    $owner = $this->emails->get($request->principalId);
    if (!$owner->verified || null === $owner->verifiedAt) {
      $this->deny();
    }
    $id = OrganizationReplayInput::identifier($request->uriVariables['requestId'] ?? $storedProof?->metadata['resourceId'] ?? null);
    if (null === $id) {
      return;
    }
    $row = $this->connection->fetchAssociative('SELECT organization_id, user_id, email, created_at FROM organization_join_requests WHERE id = :id', ['id' => $id]);
    if (false === $row || ($row['user_id'] ?? null) !== $request->principalId || ($row['organization_id'] ?? null) !== $organizationId || !is_string($row['email'] ?? null) || strtolower($row['email']) !== strtolower($owner->email) || !is_string($row['created_at'] ?? null) || $owner->verifiedAt > new DateTimeImmutable($row['created_at'])) {
      $this->deny();
    }
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request current caller
   *
   * @return bool current platform restore branch
   */
  private function platformRestore(HttpIdempotencyAuthorizationRequest $request): bool
  {
    $account = $this->accounts->current($request->principalId);

    return null !== $account && $account->active && $account->tenantId === $request->tenantId && $this->platform->permitsKey($account, 'ROLE_ADMIN');
  }

  /**
   * @param string $kind explicit owner kind
   * @param string $id current target
   *
   * @return string|null current owner scope
   */
  private function targetOrganization(string $kind, string $id): ?string
  {
    $table = self::TABLES[$kind] ?? null;
    if (null === $table) {
      return null;
    }
    $organization = $this->connection->fetchOne('SELECT organization_id FROM ' . $table . ' WHERE id = :id', ['id' => $id]);

    return is_string($organization) ? $organization : null;
  }

  /**
   * @param string|null $known trusted scope
   * @param string|null $current current target scope
   *
   * @return string exact matching owner scope
   */
  private function sameScope(?string $known, ?string $current): string
  {
    if (null === $current || (null !== $known && $known !== $current)) {
      throw OrganizationNotFoundException::withId($known ?? $current ?? 'unknown');
    }

    return $current;
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request trusted route
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
  }

  /**
   * @return never deny before any receipt body is opened
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }

  /**
   * Method authorizePlan
   *
   * Keeps plan existence and deleted-plan proof checks before platform authorization.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param IdempotencyProof|null $storedProof verified original receipt
   *
   * @return array<string, string> current plan authority
   */
  private function authorizePlan(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $name = $request->operation->name;
    $id = OrganizationReplayInput::identifier($request->uriVariables['id'] ?? $storedProof?->metadata['resourceId'] ?? null);
    if (null !== $id && false === $this->connection->fetchOne('SELECT id FROM plans WHERE id = :id', ['id' => $id]) && (null === $storedProof || 'deletePlan' !== $name)) {
      throw OrganizationNotFoundException::withId($id);
    }

    return $this->authorizer->authorize($request, $storedProof, null);
  }

  /**
   * Method routeScopes
   *
   * Resolves owner route targets and retains signed scope only for supported removal operations.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param string|null $organizationId resolved owner organization
   *
   * @return array{string|null, array<string, string>} resolved scope and facts
   */
  private function routeScopes(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $organizationId): array
  {
    $name = $request->operation->name;
    $scopeFacts = [];
    foreach (['memberId' => 'organization_member', 'roleId' => 'organization_role', 'teamId' => 'team', 'domainId' => 'organization_domain', 'requestId' => 'organization_join_request', 'invitationId' => 'organization_invitation'] as $variable => $targetKind) {
      $id = OrganizationReplayInput::identifier($request->uriVariables[$variable] ?? null);
      if (null === $id) {
        continue;
      }
      $targetOrganization = $this->targetOrganization($targetKind, $id);
      if (null === $targetOrganization) {
        if (null === $storedProof || !in_array($name, ['deleteOrganizationRole', 'deleteTeam', 'organization_join_remove_domain', 'removeOrganizationRoleFromMember', 'removeTeamMember', 'removeOrganizationMember'], true)) {
          throw OrganizationNotFoundException::withId($id);
        }
        $targetOrganization = $storedProof->metadata['scope.' . $variable] ?? null;
      }
      $organizationId = $this->sameScope($organizationId, $targetOrganization);
      $scopeFacts['scope.' . $variable] = $organizationId;
    }

    return [$organizationId, $scopeFacts];
  }

  /**
   * Method storedResourceScope
   *
   * Rechecks the original resource kind, organization and invitation-created member owner.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param string|null $organizationId resolved owner organization
   *
   * @return string|null verified resource scope
   */
  private function storedResourceScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $organizationId): ?string
  {
    $kind = $request->operation->resourceKind;
    $name = $request->operation->name;
    if (null !== $storedProof && isset($storedProof->metadata['resourceId'])) {
      if (($storedProof->metadata['resourceKind'] ?? null) !== $kind) {
        $this->deny();
      }
      $resourceId = $storedProof->metadata['resourceId'];
      $resourceOrganization = 'organization' === $kind ? $resourceId : $this->targetOrganization($kind ?? '', $resourceId);
      $organizationId = $this->sameScope($organizationId, $resourceOrganization);
      if ('acceptOrganizationInvitation' === $name && $this->connection->fetchOne('SELECT user_id FROM organization_members WHERE id = :id', ['id' => $resourceId]) !== $request->principalId) {
        $this->deny();
      }
    }

    return $organizationId;
  }

  /**
   * Method tokenInvitationScope
   *
   * Binds invitation tokens to the current email owner before accepting their organization scope.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param array<string, mixed> $fields canonical submitted scope references
   * @param string|null $organizationId resolved owner organization
   * @param array<string, string> $scopeFacts captured owner scope facts
   *
   * @return array{string|null, array<string, string>} invitation scope and facts
   */
  private function tokenInvitationScope(HttpIdempotencyAuthorizationRequest $request, array $fields, ?string $organizationId, array $scopeFacts): array
  {
    $name = $request->operation->name;
    if ('acceptOrganizationInvitation' === $name) {
      $token = $fields['token'] ?? null;
      if (!is_string($token) || '' === $token) {
        $this->deny();
      }
      $invitation = $this->connection->fetchAssociative('SELECT id, organization_id, email FROM organization_invitations WHERE token_hash = :tokenHash', ['tokenHash' => hash('sha256', $token)]);
      if (false === $invitation || !is_string($invitation['organization_id'] ?? null) || !is_string($invitation['id'] ?? null) || !is_string($invitation['email'] ?? null) || strtolower($this->emails->get($request->principalId)->email) !== strtolower($invitation['email'])) {
        $this->deny();
      }
      $organizationId = $this->sameScope($organizationId, $invitation['organization_id']);
      $scopeFacts['invitationId'] = $invitation['id'];
    }

    return [$organizationId, $scopeFacts];
  }

  /**
   * Method currentOrganizationScope
   *
   * Rechecks organization existence and initial ownership transfer before submitted owner references.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param string|null $organizationId resolved owner organization
   * @param array<string, mixed> $fields canonical submitted scope references
   *
   * @return void
   */
  private function currentOrganizationScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $organizationId, array $fields): void
  {
    $name = $request->operation->name;
    if (null !== $organizationId) {
      $row = $this->connection->fetchAssociative('SELECT id, owner_user_id FROM organizations WHERE id = :id', ['id' => $organizationId]);
      if (false === $row) {
        throw OrganizationNotFoundException::withId($organizationId);
      }
      if ('transferOrganizationOwnership' === $name && null === $storedProof && ($row['owner_user_id'] ?? null) !== $request->principalId) {
        $this->deny();
      }
      $this->payloadScopes($fields, $organizationId);
    }
  }

  /**
   * Method assertInvitationRecipient
   *
   * Requires the current verified recipient for invitation-by-identifier acceptance.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   *
   * @return void
   */
  private function assertInvitationRecipient(HttpIdempotencyAuthorizationRequest $request): void
  {
    $name = $request->operation->name;
    if ('organization_join_accept_invitation_by_id' === $name) {
      $invitationId = OrganizationReplayInput::identifier($request->uriVariables['invitationId'] ?? null);
      $email = null === $invitationId ? false : $this->connection->fetchOne('SELECT email FROM organization_invitations WHERE id = :id', ['id' => $invitationId]);
      $owner = $this->emails->get($request->principalId);
      if (!is_string($email) || !$owner->verified || strtolower($owner->email) !== strtolower($email)) {
        $this->deny();
      }
    }
  }

  /**
   * Method assertCurrentAuthority
   *
   * Rechecks current permissions and active membership before opening stored response data.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current route and caller
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param string|null $organizationId resolved owner organization
   * @param bool $platformRestore current native restore authority
   * @param bool $privateRequest verified applicant-only operation
   *
   * @return void
   */
  private function assertCurrentAuthority(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $organizationId, bool $platformRestore, bool $privateRequest): void
  {
    $name = $request->operation->name;
    $permissions = $platformRestore ? [] : $this->permissions($name);
    if (null !== $organizationId) {
      foreach ($permissions as $permission) {
        if (!$this->organizations->hasPermission($request->principalId, $organizationId, $permission)) {
          $this->deny();
        }
      }
      $membershipRequired = !in_array($name, ['acceptOrganizationInvitation', 'organization_join_accept_invitation_by_id', 'organization_join_join'], true) || null !== $storedProof;
      if (!$platformRestore && !$privateRequest && $membershipRequired && !$this->organizations->isMemberOf($request->principalId, $organizationId)) {
        $this->deny();
      }
    }
  }

  /**
   * Method assertScopeFacts
   *
   * Refuses a changed owner route scope before decoding any recorded response.
   *
   * @access private
   *
   * @param IdempotencyProof|null $storedProof verified original receipt
   * @param array<string, string> $scopeFacts freshly resolved owner facts
   *
   * @return void
   */
  private function assertScopeFacts(?IdempotencyProof $storedProof, array $scopeFacts): void
  {
    if (null === $storedProof) {
      return;
    }
    foreach ($scopeFacts as $key => $value) {
      if (isset($storedProof->metadata[$key]) && $storedProof->metadata[$key] !== $value) {
        $this->deny();
      }
    }
  }
  // #endregion
}

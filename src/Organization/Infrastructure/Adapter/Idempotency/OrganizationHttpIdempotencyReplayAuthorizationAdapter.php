<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Contract\Exception\OrganizationNotFoundException;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
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
    $organizationId = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if ('plan' === $kind) {
      $id = $this->identifier($request->uriVariables['id'] ?? $storedProof?->metadata['resourceId'] ?? null);
      if (null !== $id && false === $this->connection->fetchOne('SELECT id FROM plans WHERE id = :id', ['id' => $id]) && (null === $storedProof || 'deletePlan' !== $name)) {
        throw OrganizationNotFoundException::withId($id);
      }

      return $this->authorizer->authorize($request, $storedProof, null);
    }
    if (null === $organizationId && 'organization' === $kind) {
      $organizationId = $this->identifier($request->uriVariables['id'] ?? $storedProof?->metadata['organizationId'] ?? null);
    }
    $scopeFacts = [];
    foreach (['memberId' => 'organization_member', 'roleId' => 'organization_role', 'teamId' => 'team', 'domainId' => 'organization_domain', 'requestId' => 'organization_join_request', 'invitationId' => 'organization_invitation'] as $variable => $targetKind) {
      $id = $this->identifier($request->uriVariables[$variable] ?? null);
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
    $fields = $this->fields($request);
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
    $privateRequest = in_array($name, ['organization_join_request', 'organization_join_cancel_request'], true);
    if ($privateRequest) {
      $this->assertApplicant($request, $storedProof, $organizationId);
    }
    if ('organization_join_accept_invitation_by_id' === $name) {
      $invitationId = $this->identifier($request->uriVariables['invitationId'] ?? null);
      $email = null === $invitationId ? false : $this->connection->fetchOne('SELECT email FROM organization_invitations WHERE id = :id', ['id' => $invitationId]);
      $owner = $this->emails->get($request->principalId);
      if (!is_string($email) || !$owner->verified || strtolower($owner->email) !== strtolower($email)) {
        $this->deny();
      }
    }
    $platformRestore = 'restoreOrganization' === $name && $this->platformRestore($request);
    if (null !== $storedProof && '1' === ($storedProof->metadata['platformRestore'] ?? null) && !$platformRestore) {
      $this->deny();
    }
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
    if (null !== $storedProof) {
      foreach ($scopeFacts as $key => $value) {
        if (isset($storedProof->metadata[$key]) && $storedProof->metadata[$key] !== $value) {
          $this->deny();
        }
      }
    }
    $proof = $this->authorizer->authorize($request, $storedProof, $organizationId);
    if (null === $storedProof) {
      return [...$proof, ...$scopeFacts, 'platformRestore' => $platformRestore ? '1' : '0'];
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
      $id = $this->identifier($fields[$field] ?? null);
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
    $id = $this->identifier($request->uriVariables['requestId'] ?? $storedProof?->metadata['resourceId'] ?? null);
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
   * @param mixed $value trusted route or parsed input value
   *
   * @return string|null valid optional identifier
   */
  private function identifier(mixed $value): ?string
  {
    if (null !== $value && (!is_string($value) || '' === $value)) {
      $this->deny();
    }

    return $value;
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request parsed canonical request
   *
   * @return array<string,mixed> original JSON or multipart scalar fields
   */
  private function fields(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    if (!is_array($body)) {
      return [];
    }
    $value = $body['value'] ?? null;
    $source = is_array($value) ? ($value['fields'] ?? null) : ($body['fields'] ?? null);
    $fields = [];
    if (is_array($source)) {
      foreach ($source as $key => $field) {
        if (is_string($key)) {
          $fields[$key] = $field;
        }
      }
    }

    return $fields;
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
  // #endregion
}

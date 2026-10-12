<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationMemberScopePort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_string;

/**
 * Class WorkloadHttpIdempotencyReplayAuthorizationAdapter.
 *
 * Rechecks uncached owner scope and current grants before restoring an original immutable response.
 *
 * @category Adapter
 */
final readonly class WorkloadHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current caller and signed original requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations uncached effective organization authority
   * @param Connection $connection explicitly wired owning main database
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection, private FreshOrganizationMemberScopePort $members)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (WorkloadHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name) {
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
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $organization = $this->organization($request);
    $this->permission($request, $organization, 'organization.workload.manage');
    $settings = 'workload_settings_save' === $request->operation->name;
    $member = $settings ? null : $this->identifier($request->uriVariables['memberId'] ?? null);
    if (null !== $member && ($this->members->organizationId($member) !== $organization || !$this->members->isActive($member, $organization))) {
      $this->notFound();
    }
    $exception = in_array($request->operation->name, ['workload_exception_create', 'workload_exception_cancel'], true);
    $target = null;
    if ('workload_exception_cancel' === $request->operation->name || null !== $storedProof) {
      $target = $this->identifier('workload_exception_cancel' === $request->operation->name ? ($request->uriVariables['exceptionId'] ?? null) : ($storedProof?->metadata['resourceId'] ?? null));
      $kind = $exception ? 'workload_exception' : 'workload_week';
      if (null !== $storedProof && (($storedProof->metadata['resourceId'] ?? null) !== $target || ($storedProof->metadata['resourceKind'] ?? null) !== $kind)) {
        $this->deny();
      }
      $row = $exception
        ? $this->connection->fetchAssociative('SELECT organization_id, member_id AS scope_id FROM workload_capacity_exceptions WHERE id = :id', ['id' => $target])
        : $this->connection->fetchAssociative('SELECT organization_id, scope_id FROM workload_capacity_weeks WHERE id = :id', ['id' => $target]);
      if (false === $row) {
        if ('workload_exception_cancel' !== $request->operation->name || null === $storedProof || ($storedProof->metadata['organizationId'] ?? null) !== $organization || ($storedProof->metadata['workloadMemberId'] ?? null) !== $member) {
          $this->notFound();
        }
      } elseif (($row['organization_id'] ?? null) !== $organization || ($row['scope_id'] ?? null) !== ($member ?? $organization)) {
        $this->notFound();
      }
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['workloadMemberId'] = $member ?? '';
    if (null !== $target) {
      $facts['targetId'] = $target;
    }

    return $facts;
  }

  /**
   * Method organization.
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated route scope
   *
   * @return string exact active organization scope
   */
  private function organization(HttpIdempotencyAuthorizationRequest $request): string
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound();
    }

    return $organization;
  }

  /**
   * Method identifier.
   *
   * @param mixed $value owner resource identity
   *
   * @return string validated UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound();
    }

    return $value;
  }

  /**
   * Method permission.
   *
   * @param HttpIdempotencyAuthorizationRequest $request verified actor
   * @param string $organization exact owner scope
   * @param string $permission explicit registered owner grant
   *
   * @return void
   */
  private function permission(HttpIdempotencyAuthorizationRequest $request, string $organization, string $permission): void
  {
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
  }

  /**
   * Method notFound.
   *
   * @return never preserves the owner's hidden-scope 404 contract
   */
  private function notFound(): never
  {
    throw new \Workload\Domain\Exception\WorkloadNotFoundException('Capacity scope not found.');
  }

  /**
   * Method deny.
   *
   * @return never denies before any receipt result constructor is invoked
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationMemberScopePort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_array;
use function is_string;

/**
 * Class MaintenanceCostHttpIdempotencyReplayAuthorizationAdapter.
 *
 * Rechecks uncached owner scope and current grants before restoring an original immutable response.
 *
 * @category Adapter
 */
final readonly class MaintenanceCostHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
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
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection, private FreshOrganizationMemberScopePort $members, private FreshInterventionScopePort $interventions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (MaintenanceCostHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    $this->permission($request, $organization, 'organization.maintenance_cost.manage');
    $cost = in_array($request->operation->name, ['maintenance_cost_planning', 'maintenance_cost_expense'], true);
    $target = null;
    if ($cost) {
      $this->permission($request, $organization, 'organization.maintenance_cost.read');
      $target = $this->identifier($request->uriVariables['id'] ?? null);
      if ($this->interventions->organizationId($target) !== $organization) {
        $this->notFound();
      }
      if (null !== $storedProof && (($storedProof->metadata['resourceId'] ?? null) !== $target || ($storedProof->metadata['resourceKind'] ?? null) !== 'maintenance_intervention')) {
        $this->deny();
      }
    } elseif ('maintenance_cost_rates_create' === $request->operation->name) {
      $member = $this->identifier($this->body($request)['memberId'] ?? null);
      if ($this->members->organizationId($member) !== $organization) {
        $this->notFound();
      }
      if (null !== $storedProof) {
        $target = $this->identifier($storedProof->metadata['resourceId'] ?? null);
        if (($storedProof->metadata['resourceKind'] ?? null) !== 'maintenance_rate') {
          $this->deny();
        }
        $row = $this->connection->fetchAssociative('SELECT organization_id, member_id FROM maintenance_cost_hourly_rates WHERE id = :id', ['id' => $target]);
        if (false === $row || ($row['organization_id'] ?? null) !== $organization || ($row['member_id'] ?? null) !== $member) {
          $this->notFound();
        }
      }
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    if (null !== $target) {
      $facts['targetId'] = $target;
    }

    return $facts;
  }

  /**
   * Method body.
   *
   * @param HttpIdempotencyAuthorizationRequest $request canonical wire input
   *
   * @return array<array-key, mixed> explicitly tagged JSON object fields
   */
  private function body(HttpIdempotencyAuthorizationRequest $request): array
  {
    $body = $request->canonicalInput['body'] ?? null;
    $value = is_array($body) ? ($body['value'] ?? null) : null;
    if (!is_array($value) || 'object' !== ($value['kind'] ?? null) || !is_array($value['fields'] ?? null)) {
      return [];
    }

    return $value['fields'];
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
    throw \MaintenanceCost\Domain\Exception\MaintenanceCostException::notFound();
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

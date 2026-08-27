<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots;

use Compliance\Application\Contract\SafetyRegisterSnapshotView;
use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function sprintf;

/**
 * UseCase ListSafetyRegisterSnapshotsHandler.
 *
 * Lists an organization's archived safety register snapshots (metadata
 * only), most recently generated first. Gated exactly like the live export
 * and the snapshot creation: `resolveAccess` on
 * `organization.compliance.export` first (outside scope answers 404), then
 * the pro/max plan entitlement.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSafetyRegisterSnapshotsHandler implements QueryHandler
{
  // #region Constants
  private const string EXPORT_PERMISSION = 'organization.compliance.export';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param ComplianceExportEntitlementPort $entitlement the export entitlement port
   * @param SafetyRegisterSnapshotRepositoryPort $repository the snapshot repository port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ComplianceExportEntitlementPort $entitlement,
    private SafetyRegisterSnapshotRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListSafetyRegisterSnapshotsQuery $query the query payload
   *
   * @throws ComplianceNotFoundException if the organization is outside the caller's scope
   * @throws ComplianceAccessDeniedException if the caller lacks the export permission
   * @throws ComplianceExportNotEntitledException if the organization's plan does not entitle it to the register
   *
   * @return ListSafetyRegisterSnapshotsResult the page of snapshot metadata
   */
  public function __invoke(ListSafetyRegisterSnapshotsQuery $query): ListSafetyRegisterSnapshotsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, self::EXPORT_PERMISSION);
    if ($decision->isOutsideScope()) {
      throw ComplianceNotFoundException::organizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new ComplianceAccessDeniedException(sprintf('Missing required permission "%s".', self::EXPORT_PERMISSION));
    }

    if (!$this->entitlement->isExportEntitled($query->organizationId)) {
      throw ComplianceExportNotEntitledException::planTooLow($query->organizationId);
    }

    $offset = ($query->page - 1) * $query->itemsPerPage;
    $snapshots = $this->repository->listByOrganization($query->organizationId, $query->itemsPerPage, $offset);

    return new ListSafetyRegisterSnapshotsResult(
      items: array_map(self::toView(...), $snapshots),
      page: $query->page,
      itemsPerPage: $query->itemsPerPage,
      total: $this->repository->countByOrganization($query->organizationId),
    );
  }

  /**
   * Method toView.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshot $snapshot the domain snapshot
   *
   * @return SafetyRegisterSnapshotView the read-model row
   */
  private static function toView(SafetyRegisterSnapshot $snapshot): SafetyRegisterSnapshotView
  {
    return new SafetyRegisterSnapshotView(
      id: (string) $snapshot->id(),
      organizationId: $snapshot->organizationId(),
      facilityId: $snapshot->facilityId(),
      scope: $snapshot->scope(),
      generatedAt: $snapshot->generatedAt(),
      generatedByUserId: $snapshot->generatedByUserId(),
      contentHash: $snapshot->contentHash(),
      sizeBytes: $snapshot->sizeBytes(),
      createdAt: $snapshot->createdAt()->format('Y-m-d\\TH:i:sP'),
    );
  }
  // #endregion
}

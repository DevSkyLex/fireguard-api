<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent;

use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

use function sprintf;

/**
 * UseCase GetSafetyRegisterSnapshotContentHandler.
 *
 * Returns the archived PDF bytes of one safety register snapshot for
 * download. Gated exactly like the live export: `resolveAccess` on
 * `organization.compliance.export` first (outside scope answers 404), then
 * the pro/max plan entitlement. The snapshot lookup itself is
 * organization-scoped, so another organization's snapshot — or an unknown
 * identifier — answers the same 404.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSafetyRegisterSnapshotContentHandler implements QueryHandler
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
   * @param FileStoragePort $fileStorage the file storage port holding the PDF bytes
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ComplianceExportEntitlementPort $entitlement,
    private SafetyRegisterSnapshotRepositoryPort $repository,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetSafetyRegisterSnapshotContentQuery $query the query payload
   *
   * @throws ComplianceNotFoundException if the organization is outside the caller's scope, or the snapshot is unknown within it
   * @throws ComplianceAccessDeniedException if the caller lacks the export permission
   * @throws ComplianceExportNotEntitledException if the organization's plan does not entitle it to the register
   *
   * @return GetSafetyRegisterSnapshotContentResult the archived PDF bytes and their metadata
   */
  public function __invoke(GetSafetyRegisterSnapshotContentQuery $query): GetSafetyRegisterSnapshotContentResult
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

    $snapshot = $this->findSnapshot($query);
    $contents = $this->fileStorage->read($snapshot->storagePath());

    return new GetSafetyRegisterSnapshotContentResult(
      contents: $contents,
      snapshotId: (string) $snapshot->id(),
      facilityId: $snapshot->facilityId(),
      generatedAt: $snapshot->generatedAt(),
      contentHash: $snapshot->contentHash(),
      sizeBytes: $snapshot->sizeBytes(),
    );
  }

  /**
   * Method findSnapshot.
   *
   * @since 1.0.0
   *
   * @param GetSafetyRegisterSnapshotContentQuery $query the query payload
   *
   * @throws ComplianceNotFoundException if the snapshot is unknown within the organization
   *
   * @return SafetyRegisterSnapshot the organization-scoped snapshot
   */
  private function findSnapshot(GetSafetyRegisterSnapshotContentQuery $query): SafetyRegisterSnapshot
  {
    try {
      $id = SafetyRegisterSnapshotId::fromString($query->snapshotId);
    } catch (InvalidValueException) {
      throw ComplianceNotFoundException::snapshotNotFound($query->snapshotId);
    }

    $snapshot = $this->repository->findForOrganization($id, $query->organizationId);
    if (!$snapshot instanceof SafetyRegisterSnapshot) {
      throw ComplianceNotFoundException::snapshotNotFound($query->snapshotId);
    }

    return $snapshot;
  }
  // #endregion
}

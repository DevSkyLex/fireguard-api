<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot;

use Compliance\Application\Contract\SafetyRegisterSnapshotView;
use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterPdfRendererPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Application\Service\SafetyRegisterContextBuilder;
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Event\SafetyRegisterSnapshotCreatedEvent;
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileStoragePort};
use Throwable;

use function hash;
use function sprintf;
use function strlen;

/**
 * UseCase CreateSafetyRegisterSnapshotHandler.
 *
 * Archives the regulatory "registre de sécurité" as a dated, immutable
 * snapshot: same permission (`organization.compliance.export`) and plan
 * entitlement gate as the live export, same read-model, same rendering
 * pipeline (`SafetyRegisterContextBuilder` + `SafetyRegisterPdfRendererPort`)
 * — the archived PDF is exactly the document the live endpoint would have
 * streamed at that instant. The PDF bytes go to file storage
 * (`compliance/registers/<orgId>/<snapshotId>.pdf`), the metadata row —
 * including the SHA-256 content hash proving later integrity — to the main
 * database, and the audit event is dispatched only after the durable save.
 *
 * `resolveAccess` runs first: an organization outside the caller's scope
 * answers 404 (the same as an unknown identifier), a member without the
 * permission answers 403, and an entitled-permission member on a free plan
 * answers the distinct not-entitled 403.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSafetyRegisterSnapshotHandler implements CommandHandler
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
   * @param QueryBusPort $queryBus the query bus resolving the register read-model
   * @param SafetyRegisterContextBuilder $contextBuilder the shared register context pipeline
   * @param OrganizationDocumentBrandingPort $branding the organization document branding port
   * @param SafetyRegisterPdfRendererPort $renderer the PDF renderer port
   * @param FileStoragePort $fileStorage the file storage port holding the PDF bytes
   * @param SafetyRegisterSnapshotRepositoryPort $repository the snapshot repository port
   * @param UuidFactory $uuidFactory the uuid factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ComplianceExportEntitlementPort $entitlement,
    private QueryBusPort $queryBus,
    private SafetyRegisterContextBuilder $contextBuilder,
    private OrganizationDocumentBrandingPort $branding,
    private SafetyRegisterPdfRendererPort $renderer,
    private FileStoragePort $fileStorage,
    private SafetyRegisterSnapshotRepositoryPort $repository,
    private UuidFactory $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateSafetyRegisterSnapshotCommand $command the command payload
   *
   * @throws ComplianceNotFoundException if the organization is outside the caller's scope, or the facility is unknown
   * @throws ComplianceAccessDeniedException if the caller lacks the export permission
   * @throws ComplianceExportNotEntitledException if the organization's plan does not entitle it to the register
   *
   * @return CreateSafetyRegisterSnapshotResult the archived snapshot metadata
   */
  public function __invoke(CreateSafetyRegisterSnapshotCommand $command): CreateSafetyRegisterSnapshotResult
  {
    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, self::EXPORT_PERMISSION);
    if ($decision->isOutsideScope()) {
      throw ComplianceNotFoundException::organizationScope($command->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new ComplianceAccessDeniedException(sprintf('Missing required permission "%s".', self::EXPORT_PERMISSION));
    }

    if (!$this->entitlement->isExportEntitled($command->organizationId)) {
      throw ComplianceExportNotEntitledException::planTooLow($command->organizationId);
    }

    [$context, $generatedAt] = null !== $command->facilityId
      ? $this->buildFacilityContext($command->organizationId, $command->facilityId, $command->userId)
      : $this->buildOrganizationContext($command->organizationId, $command->userId);

    $planKey = $this->entitlement->resolvePlanKey($command->organizationId) ?? 'unknown';
    $context['organizationId'] = $command->organizationId;
    $context['facilityId'] = $command->facilityId;
    $context['planKey'] = $planKey;

    $context = $this->contextBuilder->localize($context, $this->branding->getDocumentBranding($command->organizationId));

    $pdf = $this->renderer->render($context);

    /** @var SafetyRegisterSnapshotId $id */
    $id = $this->uuidFactory->create(SafetyRegisterSnapshotId::class);
    $storagePath = sprintf('compliance/registers/%s/%s.pdf', $command->organizationId, (string) $id);

    $snapshot = SafetyRegisterSnapshot::create(
      id: $id,
      organizationId: $command->organizationId,
      facilityId: $command->facilityId,
      generatedAt: $generatedAt,
      generatedByUserId: $command->userId,
      contentHash: hash('sha256', $pdf),
      sizeBytes: strlen($pdf),
      storagePath: $storagePath,
    );

    $this->fileStorage->write($storagePath, $pdf);

    try {
      $this->repository->save($snapshot);
    } catch (Throwable $exception) {
      $this->fileStorage->delete($storagePath);

      throw $exception;
    }

    $this->eventDispatcher->dispatch(new SafetyRegisterSnapshotCreatedEvent(
      snapshotId: (string) $id,
      organizationId: $command->organizationId,
      facilityId: $command->facilityId,
      actorUserId: $command->userId,
      planKey: $planKey,
      scope: $snapshot->scope(),
      generatedAt: $generatedAt,
      contentHash: $snapshot->contentHash(),
      sizeBytes: $snapshot->sizeBytes(),
    ));

    return new CreateSafetyRegisterSnapshotResult(snapshot: new SafetyRegisterSnapshotView(
      id: (string) $id,
      organizationId: $snapshot->organizationId(),
      facilityId: $snapshot->facilityId(),
      scope: $snapshot->scope(),
      generatedAt: $snapshot->generatedAt(),
      generatedByUserId: $snapshot->generatedByUserId(),
      contentHash: $snapshot->contentHash(),
      sizeBytes: $snapshot->sizeBytes(),
      createdAt: $snapshot->createdAt()->format('Y-m-d\\TH:i:sP'),
    ));
  }

  /**
   * Method buildOrganizationContext.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the requesting user identifier
   *
   * @return array{0: array<string, mixed>, 1: string} the raw context and its generation datetime
   */
  private function buildOrganizationContext(string $organizationId, string $userId): array
  {
    /** @var GetComplianceOverviewResult $result */
    $result = $this->queryBus->ask(new GetComplianceOverviewQuery(organizationId: $organizationId, userId: $userId));

    return [$this->contextBuilder->buildOrganizationContext($result), $result->generatedAt];
  }

  /**
   * Method buildFacilityContext.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   * @param string $userId the requesting user identifier
   *
   * @return array{0: array<string, mixed>, 1: string} the raw context and its generation datetime
   */
  private function buildFacilityContext(string $organizationId, string $facilityId, string $userId): array
  {
    /** @var GetFacilityComplianceResult $result */
    $result = $this->queryBus->ask(new GetFacilityComplianceQuery(organizationId: $organizationId, facilityId: $facilityId, userId: $userId));

    return [$this->contextBuilder->buildFacilityContext($result), $result->generatedAt];
  }
  // #endregion
}

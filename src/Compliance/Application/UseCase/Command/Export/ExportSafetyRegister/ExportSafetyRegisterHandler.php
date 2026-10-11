<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Export\ExportSafetyRegister;

use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterPdfRendererPort};
use Compliance\Application\Service\SafetyRegisterContextBuilder;
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use Compliance\Domain\Exception\ComplianceExportNotEntitledException;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function sprintf;

/**
 * Class ExportSafetyRegisterHandler
 *
 * Authorizes and renders an organization or facility register, then records its export event.
 *
 * @category Handler
 */
final readonly class ExportSafetyRegisterHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant EXPORT_PERMISSION
   */
  private const string EXPORT_PERMISSION = 'organization.compliance.export';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param OrganizationDocumentBrandingPort $branding the organization document branding port
   * @param ComplianceExportEntitlementPort $entitlement the export entitlement port
   * @param SafetyRegisterPdfRendererPort $renderer the PDF renderer port
   * @param SafetyRegisterContextBuilder $contextBuilder the shared register context pipeline
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private OrganizationDocumentBrandingPort $branding,
    private ComplianceExportEntitlementPort $entitlement,
    private SafetyRegisterPdfRendererPort $renderer,
    private SafetyRegisterContextBuilder $contextBuilder,
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
   * @param ExportSafetyRegisterCommand $command the export identity and scope
   *
   * @return ExportSafetyRegisterResult rendered PDF bytes and attachment name
   */
  public function __invoke(ExportSafetyRegisterCommand $command): ExportSafetyRegisterResult
  {
    $organizationId = $command->organizationId;
    $facilityId = $command->facilityId;
    $this->authorization->assertGrantedPermissions($command->userId, $organizationId, [self::EXPORT_PERMISSION]);

    if (!$this->entitlement->isExportEntitled($organizationId)) {
      throw ComplianceExportNotEntitledException::planTooLow($organizationId);
    }

    [$context, $generatedAt] = null !== $facilityId
      ? $this->buildFacilityContext($organizationId, $facilityId, $command->userId)
      : $this->buildOrganizationContext($organizationId, $command->userId);

    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';
    $context['organizationId'] = $organizationId;
    $context['facilityId'] = $facilityId;
    $context['planKey'] = $planKey;

    $branding = $this->branding->getDocumentBranding($organizationId);
    $context = $this->contextBuilder->localize($context, $branding);

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new SafetyRegisterExportedEvent(
      organizationId: $organizationId,
      facilityId: $facilityId,
      actorUserId: $command->userId,
      planKey: $planKey,
      scope: null !== $facilityId ? 'facility' : 'organization',
      generatedAt: $generatedAt,
    ));

    $fileName = sprintf(
      'registre-securite-%s-%s.pdf',
      null !== $facilityId ? $facilityId : $organizationId,
      new DateTimeImmutable()->format('Ymd-His'),
    );

    return new ExportSafetyRegisterResult($pdf, $fileName);
  }

  /**
   * @return array{0: array<string, mixed>, 1: string}
   */
  private function buildOrganizationContext(string $organizationId, string $userId): array
  {
    /** @var GetComplianceOverviewResult $result */
    $result = $this->queryBus->ask(new GetComplianceOverviewQuery(organizationId: $organizationId, userId: $userId));

    return [$this->contextBuilder->buildOrganizationContext($result), $result->generatedAt];
  }

  /**
   * @return array{0: array<string, mixed>, 1: string}
   */
  private function buildFacilityContext(string $organizationId, string $facilityId, string $userId): array
  {
    /** @var GetFacilityComplianceResult $result */
    $result = $this->queryBus->ask(new GetFacilityComplianceQuery(organizationId: $organizationId, facilityId: $facilityId, userId: $userId));

    return [$this->contextBuilder->buildFacilityContext($result), $result->generatedAt];
  }

  // #endregion
}

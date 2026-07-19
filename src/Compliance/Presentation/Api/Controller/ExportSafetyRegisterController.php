<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterPdfRendererPort};
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use Compliance\Domain\Exception\ComplianceExportNotEntitledException;
use Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory;
use Compliance\Presentation\Api\Trait\ComplianceExceptionMapperTrait;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Controller ExportSafetyRegisterController.
 *
 * Invokable API Platform controller (wired via `controller:` on the export
 * Get operations, `read`/`write`/`serialize`/`output` disabled — the
 * documented API Platform pattern for a custom action returning a raw
 * `Response`) generating the "registre de sécurité" PDF. Thin by design:
 * authenticate, assert `organization.compliance.export`, gate on the plan
 * entitlement, fetch the same read-model the JSON summary uses, render, and
 * emit the audit event. Handles BOTH the organization-wide and the
 * facility-scoped export routes: `facilityId` is only present as a route
 * attribute on the facility-scoped route.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportSafetyRegisterController extends AbstractController
{
  use ComplianceExceptionMapperTrait;

  // #region Constants
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
   * @param ComplianceExportEntitlementPort $entitlement the export entitlement port
   * @param SafetyRegisterPdfRendererPort $renderer the PDF renderer port
   * @param ComplianceSummaryOutputFactory $outputFactory the summary output factory (row-shaping reuse)
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationAuthorizationPort $authorization,
    private readonly ComplianceExportEntitlementPort $entitlement,
    private readonly SafetyRegisterPdfRendererPort $renderer,
    private readonly ComplianceSummaryOutputFactory $outputFactory,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return Response the streamed PDF response
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $rawFacilityId = $request->attributes->get('facilityId');
    $facilityId = is_string($rawFacilityId) && '' !== $rawFacilityId ? $rawFacilityId : null;

    try {
      $this->authorization->assertGrantedPermissions($user->getId(), $organizationId, [self::EXPORT_PERMISSION]);
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    }

    if (!$this->entitlement->isExportEntitled($organizationId)) {
      throw $this->mapComplianceException(ComplianceExportNotEntitledException::planTooLow($organizationId));
    }

    [$context, $generatedAt] = null !== $facilityId
      ? $this->buildFacilityContext($organizationId, $facilityId, $user->getId())
      : $this->buildOrganizationContext($organizationId, $user->getId());

    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';
    $context['organizationId'] = $organizationId;
    $context['facilityId'] = $facilityId;
    $context['planKey'] = $planKey;

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new SafetyRegisterExportedEvent(
      organizationId: $organizationId,
      facilityId: $facilityId,
      actorUserId: $user->getId(),
      planKey: $planKey,
      scope: null !== $facilityId ? 'facility' : 'organization',
      generatedAt: $generatedAt,
    ));

    $fileName = sprintf(
      'registre-securite-%s-%s.pdf',
      null !== $facilityId ? $facilityId : $organizationId,
      new DateTimeImmutable()->format('Ymd-His'),
    );

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * @return array{0: array<string, mixed>, 1: string}
   */
  private function buildOrganizationContext(string $organizationId, string $userId): array
  {
    try {
      /** @var GetComplianceOverviewResult $result */
      $result = $this->queryBus->ask(new GetComplianceOverviewQuery(organizationId: $organizationId, userId: $userId));
    } catch (Throwable $exception) {
      throw $this->mapComplianceException($exception);
    }

    $output = $this->outputFactory->fromOrganizationRegister(
      organizationId: $organizationId,
      generatedAt: $result->generatedAt,
      organizationStatus: $result->organizationStatus->value,
      totals: $result->totals,
      facilities: $result->facilities,
    );

    return [
      [
        'scope' => 'organization',
        'generatedAt' => $output->generatedAt,
        'organizationStatus' => $output->organizationStatus,
        'totals' => $output->totals,
        'facilities' => $output->facilities,
      ],
      $result->generatedAt,
    ];
  }

  /**
   * @return array{0: array<string, mixed>, 1: string}
   */
  private function buildFacilityContext(string $organizationId, string $facilityId, string $userId): array
  {
    try {
      /** @var GetFacilityComplianceResult $result */
      $result = $this->queryBus->ask(new GetFacilityComplianceQuery(organizationId: $organizationId, facilityId: $facilityId, userId: $userId));
    } catch (Throwable $exception) {
      throw $this->mapComplianceException($exception);
    }

    $output = $this->outputFactory->fromFacilityRegister(
      organizationId: $organizationId,
      generatedAt: $result->generatedAt,
      facility: $result->facility,
    );

    return [
      [
        'scope' => 'facility',
        'generatedAt' => $output->generatedAt,
        'organizationStatus' => $output->organizationStatus,
        'totals' => $output->totals,
        'facilities' => $output->facilities,
      ],
      $result->generatedAt,
    ];
  }
  // #endregion
}

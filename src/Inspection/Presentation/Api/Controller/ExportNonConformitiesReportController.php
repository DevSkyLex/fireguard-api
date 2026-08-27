<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Export\NonConformityExportRow;
use Inspection\Application\Port\Outbound\{InspectionReportEntitlementPort, NonConformityReportPdfRendererPort};
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Inspection\Domain\Event\Export\NonConformitiesReportExportedEvent;
use Inspection\Domain\Exception\InspectionReportNotEntitledException;
use Inspection\Presentation\Api\Service\NonConformityExportCriteriaFactory;
use Inspection\Presentation\Api\Trait\InspectionExportExceptionMapperTrait;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Document\DocumentDateFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function array_keys;
use function array_map;
use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Controller ExportNonConformitiesReportController.
 *
 * Invokable API Platform controller (wired via `controller:` on a `Get`
 * operation, `read`/`write`/`deserialize`/`serialize`/`output` disabled —
 * mirrors `Compliance\...\ExportSafetyRegisterController`) generating the
 * organization-scoped non-conformities PDF report, grouped by severity.
 * Thin by design: authenticate, resolve `organization.inspection.read`
 * through the module's standard `resolveAccess` 403/404 split, gate on the
 * plan entitlement (the SAME `pro`/`max` gate as the Compliance safety
 * register), parse the SAME `severity`/`status` filters as the CSV export
 * (`NonConformityExportCriteriaFactory` is reused verbatim), dispatch the
 * SAME `ExportNonConformitiesQuery` the CSV export uses (its handler owns
 * the row cap, the bulk naming and the `ageInDays` computation), group the
 * rows by severity (presentation shaping only), render, and emit the audit
 * event.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportNonConformitiesReportController extends AbstractController
{
  use InspectionExportExceptionMapperTrait;

  // #region Constants
  private const string READ_PERMISSION = 'organization.inspection.read';

  /**
   * Constant SEVERITY_ORDER.
   *
   * Display order of the severity groups, most critical first.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array SEVERITY_ORDER = ['critical', 'high', 'medium', 'low'];
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
   * @param InspectionReportEntitlementPort $entitlement the report entitlement port
   * @param NonConformityReportPdfRendererPort $renderer the PDF renderer port
   * @param NonConformityExportCriteriaFactory $criteriaFactory the filter parser (CSV-export reuse)
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationAuthorizationPort $authorization,
    private readonly OrganizationDocumentBrandingPort $branding,
    private readonly InspectionReportEntitlementPort $entitlement,
    private readonly NonConformityReportPdfRendererPort $renderer,
    private readonly NonConformityExportCriteriaFactory $criteriaFactory,
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
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, self::READ_PERMISSION);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    if (!$this->entitlement->isExportEntitled($organizationId)) {
      $notEntitled = InspectionReportNotEntitledException::planTooLow($organizationId);

      throw new AccessDeniedHttpException($notEntitled->getMessage(), $notEntitled);
    }

    $filters = $this->criteriaFactory->fromRequest($request);

    try {
      /** @var ExportNonConformitiesResult $result */
      $result = $this->queryBus->ask(new ExportNonConformitiesQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        filters: $filters,
      ));
    } catch (Throwable $exception) {
      throw $this->mapExportException($exception);
    }

    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';

    /** @var list<string> $filterKeys */
    $filterKeys = array_keys($filters);

    $context = $this->buildContext($result, $filters);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new NonConformitiesReportExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      rowCount: $result->total,
      filterKeys: $filterKeys,
      planKey: $planKey,
    ));

    $fileName = sprintf('non-conformities-report-%s.pdf', new DateTimeImmutable()->format('Ymd-His'));

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * Method buildContext.
   *
   * Translates the export rows into a flat Twig context grouped by
   * severity, most critical first — pure presentation shaping, the handler
   * already computed `ageInDays` and resolved every display name.
   *
   * @since 1.0.0
   *
   * @param ExportNonConformitiesResult $result the export result
   * @param array<string, mixed> $filters the applied filters
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(ExportNonConformitiesResult $result, array $filters): array
  {
    $groups = [];
    foreach (self::SEVERITY_ORDER as $severity) {
      $groups[$severity] = [];
    }

    foreach ($result->rows as $row) {
      $groups[$row->severity] ??= [];
      $groups[$row->severity][] = $this->rowContext($row);
    }

    $severityGroups = [];
    foreach ($groups as $severity => $rows) {
      $severityGroups[] = [
        'severity' => $severity,
        'count' => count($rows),
        'rows' => $rows,
      ];
    }

    $severityFilter = $filters['severity'] ?? null;
    $statusFilter = $filters['status'] ?? null;

    return [
      'total' => $result->total,
      'severityFilter' => is_string($severityFilter) ? $severityFilter : null,
      'statusFilter' => is_string($statusFilter) ? $statusFilter : null,
      'severityGroups' => $severityGroups,
      'generatedAt' => new DateTimeImmutable()->format('c'),
    ];
  }

  /**
   * Method rowContext.
   *
   * @since 1.0.0
   *
   * @param NonConformityExportRow $row the export row
   *
   * @return array<string, mixed> the row's Twig context
   */
  private function rowContext(NonConformityExportRow $row): array
  {
    return [
      'status' => $row->status,
      'ageInDays' => $row->ageInDays,
      'facilityName' => $row->facilityName ?? $row->facilityId,
      'equipmentSerialNumber' => $row->equipmentSerialNumber ?? $row->equipmentId,
      'createdAt' => $row->createdAt,
      'resolvedAt' => $row->resolvedAt,
    ];
  }

  /**
   * Method localizeContext.
   *
   * Enriches the Twig context with the organization document branding (name,
   * inlined logo, legal identity), the translation language, and dates
   * reformatted per the organization regional settings (timezone + date
   * format). Pure presentation shaping — no business decision.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the raw Twig context
   * @param OrganizationDocumentBranding $branding the organization document branding
   *
   * @return array<string, mixed> the localized Twig context
   */
  private function localizeContext(array $context, OrganizationDocumentBranding $branding): array
  {
    $formatter = new DocumentDateFormatter($branding->dateFormat, $branding->timezone);

    $context['org'] = [
      'name' => $branding->organizationName,
      'logoDataUri' => $branding->logoDataUri,
      'legalName' => $branding->legalName,
      'registrationNumber' => $branding->registrationNumber,
      'vatNumber' => $branding->vatNumber,
    ];
    $context['lang'] = $branding->language();

    $generatedAt = $context['generatedAt'] ?? null;
    $context['generatedAtFormatted'] = $formatter->formatDateTime(is_string($generatedAt) ? $generatedAt : null);

    if (isset($context['severityGroups']) && is_array($context['severityGroups'])) {
      $context['severityGroups'] = array_map(
        static function (mixed $group) use ($formatter): mixed {
          if (!is_array($group) || !isset($group['rows']) || !is_array($group['rows'])) {
            return $group;
          }

          $group['rows'] = array_map(
            static function (mixed $row) use ($formatter): mixed {
              if (!is_array($row)) {
                return $row;
              }

              $createdAt = $row['createdAt'] ?? null;
              $row['createdAt'] = $formatter->formatDate(is_string($createdAt) ? $createdAt : null);

              $resolvedAt = $row['resolvedAt'] ?? null;
              $row['resolvedAt'] = $formatter->formatDate(is_string($resolvedAt) ? $resolvedAt : null);

              return $row;
            },
            $group['rows'],
          );

          return $group;
        },
        $context['severityGroups'],
      );
    }

    return $context;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\{InspectionReportEntitlementPort, InspectionReportPdfRendererPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesQuery, NonConformityResult};
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesQuery, ListInspectionResponsesResult};
use Inspection\Domain\Event\Export\InspectionReportExportedEvent;
use Inspection\Domain\Exception\{InspectionNotFoundException, InspectionReportNotEntitledException};
use Inspection\Presentation\Api\Trait\InspectionExportExceptionMapperTrait;
use InvalidArgumentException;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Document\DocumentDateFormatter;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function implode;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Controller ExportInspectionReportController.
 *
 * Invokable API Platform controller (wired via `controller:` on a `Get`
 * operation, `read`/`write`/`deserialize`/`serialize`/`output` disabled —
 * mirrors `Compliance\...\ExportSafetyRegisterController`) generating a PDF
 * report of one inspection. Thin by design: authenticate, resolve
 * `organization.inspection.read` through the module's standard
 * `resolveAccess` 403/404 split, gate on the plan entitlement (the SAME
 * `pro`/`max` gate as the Compliance safety register — a deliberate
 * product decision, unlike the ungated intervention report), dispatch the
 * SAME read queries the JSON endpoints already use (no new handler),
 * translate the results into a Twig context, render, and emit the audit
 * event. Checklist responses reuse `ListInspectionResponsesQuery` scoped by
 * `inspectionId` (its handler defaults to `published` records — exactly
 * what a report of a submitted inspection wants); the permission check the
 * response listing normally receives from its scope resolver is performed
 * here, before the query is dispatched.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportInspectionReportController extends AbstractController
{
  use InspectionExportExceptionMapperTrait;

  // #region Constants
  private const string READ_PERMISSION = 'organization.inspection.read';

  private const int MAX_ITEMS_PER_SECTION = 200;
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
   * @param InspectionReportPdfRendererPort $renderer the PDF renderer port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationAuthorizationPort $authorization,
    private readonly OrganizationDocumentBrandingPort $branding,
    private readonly InspectionReportEntitlementPort $entitlement,
    private readonly InspectionReportPdfRendererPort $renderer,
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
    $inspectionId = $request->attributes->get('inspectionId');
    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
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

    try {
      /** @var GetInspectionResult $inspectionResult */
      $inspectionResult = $this->queryBus->ask(new GetInspectionQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
      ));

      /** @var ListInspectionResponsesResult $responsesResult */
      $responsesResult = $this->queryBus->ask(new ListInspectionResponsesQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_SECTION,
      ));

      /** @var PaginatedResult<NonConformityResult> $nonConformitiesResult */
      $nonConformitiesResult = $this->queryBus->ask(new ListNonConformitiesQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        pagination: new Pagination(offset: 0, limit: self::MAX_ITEMS_PER_SECTION),
      ));
    } catch (Throwable $exception) {
      throw $this->mapReportException($exception);
    }

    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';

    $context = $this->buildContext($inspectionResult, $responsesResult, $nonConformitiesResult);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new InspectionReportExportedEvent(
      inspectionId: $inspectionId,
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      planKey: $planKey,
    ));

    $fileName = sprintf('inspection-%s-report.pdf', $inspectionId);

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * Method mapReportException.
   *
   * Maps the (possibly bus-wrapped) domain exceptions the read queries can
   * throw to their HTTP status. `mapExportException` (from the module's
   * export mapper trait) already covers the 403/404/422 set; an
   * `InvalidArgumentException` (malformed identifier) is mapped to 400
   * first, mirroring `GetInspectionProvider`.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapReportException(Throwable $exception): Throwable
  {
    if ($exception instanceof InvalidArgumentException) {
      return new BadRequestHttpException($exception->getMessage(), $exception);
    }

    if ($exception instanceof InspectionNotFoundException) {
      return new NotFoundHttpException($exception->getMessage(), $exception);
    }

    return $this->mapExportException($exception);
  }

  /**
   * Method buildContext.
   *
   * Translates the query results into a flat Twig context — pure
   * presentation shaping, nothing here decides anything the handlers have
   * not already decided.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResult $inspection the inspection detail result
   * @param ListInspectionResponsesResult $responses the checklist responses result
   * @param PaginatedResult<NonConformityResult> $nonConformities the inspection's non-conformities result
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(
    GetInspectionResult $inspection,
    ListInspectionResponsesResult $responses,
    PaginatedResult $nonConformities,
  ): array {
    return [
      'inspectionId' => $inspection->inspectionId,
      'status' => $inspection->status,
      'result' => $inspection->result,
      'performedAt' => $inspection->performedAt,
      'inspectorType' => $inspection->inspectorType,
      'inspectorName' => $inspection->inspectorName,
      'inspectorOrganizationName' => $inspection->inspectorOrganizationName,
      'facilityName' => $inspection->facilityName,
      'equipmentSerialNumber' => $inspection->equipmentSerialNumber,
      'checklistName' => $inspection->checklistName,
      'notes' => $inspection->notes,
      'hasSignature' => null !== $inspection->signature && '' !== $inspection->signature,
      'nonConformitiesCount' => $inspection->nonConformitiesCount,
      'generatedAt' => new DateTimeImmutable()->format('c'),
      'responses' => array_map(static fn (InspectionResponseView $view): array => [
        'itemKey' => $view->itemKey,
        'value' => self::stringifyResponseValue($view->value),
      ], $responses->views),
      'nonConformities' => array_map(static fn (NonConformityResult $nonConformity): array => [
        'severity' => $nonConformity->severity,
        'status' => $nonConformity->status,
        'description' => $nonConformity->description,
        'dueAt' => $nonConformity->dueAt,
        'resolvedAt' => $nonConformity->resolvedAt,
        'createdAt' => $nonConformity->createdAt->format('c'),
      ], $nonConformities->items),
    ];
  }

  /**
   * Method stringifyResponseValue.
   *
   * Renders a checklist answer (free-form JSON: bool, number, string,
   * list…) as a display string — presentation shaping only.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw stored answer
   *
   * @return string the display string
   */
  private static function stringifyResponseValue(mixed $value): string
  {
    if (is_bool($value)) {
      return $value ? 'yes' : 'no';
    }

    if (null === $value) {
      return '';
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    if (is_array($value)) {
      $parts = [];
      foreach ($value as $item) {
        if (is_scalar($item)) {
          $parts[] = (string) $item;
        }
      }

      return implode(', ', $parts);
    }

    return '';
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

    $performedAt = $context['performedAt'] ?? null;
    $context['performedAt'] = $formatter->formatDateTime(is_string($performedAt) ? $performedAt : null);

    if (isset($context['nonConformities']) && is_array($context['nonConformities'])) {
      $context['nonConformities'] = array_map(
        static function (mixed $nonConformity) use ($formatter): mixed {
          if (!is_array($nonConformity)) {
            return $nonConformity;
          }

          foreach (['dueAt', 'resolvedAt'] as $dateKey) {
            $value = $nonConformity[$dateKey] ?? null;
            $nonConformity[$dateKey] = $formatter->formatDate(is_string($value) ? $value : null);
          }

          $createdAt = $nonConformity['createdAt'] ?? null;
          $nonConformity['createdAt'] = $formatter->formatDateTime(is_string($createdAt) ? $createdAt : null);

          return $nonConformity;
        },
        $context['nonConformities'],
      );
    }

    return $context;
  }
  // #endregion
}

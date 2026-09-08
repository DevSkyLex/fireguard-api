<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentReportEntitlementPort, EquipmentReportPdfRendererPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs\{ListMaintenanceLogsQuery, ListMaintenanceLogsResult};
use Equipment\Domain\Event\Export\EquipmentReportExportedEvent;
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentReportNotEntitledException};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Document\DocumentDateFormatter;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Controller ExportEquipmentReportController.
 *
 * Invokable API Platform controller (wired via `controller:` on a `Get`
 * operation, `read`/`write`/`deserialize`/`serialize`/`output` disabled —
 * mirrors `Compliance\...\ExportSafetyRegisterController`) generating the
 * equipment sheet PDF. Thin by design: authenticate, resolve
 * `organization.equipment.read` through the module's standard
 * `resolveAccess` 403/404 split, gate on the plan entitlement (the SAME
 * `pro`/`max` gate as the Compliance safety register — a deliberate
 * product decision, unlike the ungated intervention report), dispatch the
 * SAME read queries the JSON endpoints already use (no new handler),
 * translate the results into a Twig context, render, and emit the audit
 * event. Linked non-conformities are deliberately absent: no per-equipment
 * non-conformity port exists (`NonConformityStatisticsPort` is
 * organization-wide only), and creating one would be new business logic.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportEquipmentReportController extends AbstractController
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constants
  private const string READ_PERMISSION = 'organization.equipment.read';

  private const int MAX_ITEMS_PER_SECTION = 100;
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
   * @param EquipmentReportEntitlementPort $entitlement the report entitlement port
   * @param EquipmentReportPdfRendererPort $renderer the PDF renderer port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationAuthorizationPort $authorization,
    private readonly OrganizationDocumentBrandingPort $branding,
    private readonly EquipmentReportEntitlementPort $entitlement,
    private readonly EquipmentReportPdfRendererPort $renderer,
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
    $equipmentId = $request->attributes->get('equipmentId');
    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, self::READ_PERMISSION);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    if (!$this->entitlement->isExportEntitled($organizationId)) {
      $notEntitled = EquipmentReportNotEntitledException::planTooLow($organizationId);

      throw new AccessDeniedHttpException($notEntitled->getMessage(), $notEntitled);
    }

    try {
      /** @var GetEquipmentResult $equipmentResult */
      $equipmentResult = $this->queryBus->ask(new GetEquipmentQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      ));

      /** @var ListMaintenanceLogsResult $logsResult */
      $logsResult = $this->queryBus->ask(new ListMaintenanceLogsQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        pagination: new Pagination(offset: 0, limit: self::MAX_ITEMS_PER_SECTION),
      ));

      /** @var ListEquipmentAttachmentsResult $attachmentsResult */
      $attachmentsResult = $this->queryBus->ask(new ListEquipmentAttachmentsQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapReportException($exception);
    }

    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';

    $context = $this->buildContext($equipmentResult, $logsResult, $attachmentsResult);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new EquipmentReportExportedEvent(
      equipmentId: $equipmentId,
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      planKey: $planKey,
    ));

    $fileName = sprintf('equipment-%s-report.pdf', $equipmentId);

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * Method mapReportException.
   *
   * Maps the (possibly bus-wrapped) domain exceptions the read queries can
   * throw to their HTTP status, using the module's unwrapper trait.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return Throwable the mapped exception
   */
  private function mapReportException(Throwable $exception): Throwable
  {
    $notFound = $exception instanceof EquipmentNotFoundException
      ? $exception
      : $this->findEquipmentNotFoundException($exception);
    if ($notFound instanceof EquipmentNotFoundException) {
      return new NotFoundHttpException($notFound->getMessage(), $exception);
    }

    $invalidArgument = $exception instanceof InvalidArgumentException
      ? $exception
      : $this->findInvalidArgumentException($exception);
    if ($invalidArgument instanceof InvalidArgumentException) {
      return new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }

    return $exception;
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
   * @param GetEquipmentResult $equipment the equipment detail result
   * @param ListMaintenanceLogsResult $logs the maintenance history result
   * @param ListEquipmentAttachmentsResult $attachments the attachment index result
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(
    GetEquipmentResult $equipment,
    ListMaintenanceLogsResult $logs,
    ListEquipmentAttachmentsResult $attachments,
  ): array {
    return [
      'equipmentId' => $equipment->equipmentId,
      'type' => $equipment->type,
      'subType' => $equipment->subType,
      'brand' => $equipment->brand,
      'model' => $equipment->model,
      'serialNumber' => $equipment->serialNumber,
      'locationLabel' => $equipment->locationLabel,
      'status' => $equipment->status,
      'facilityName' => $equipment->facilityName,
      'installedAt' => $equipment->installedAt,
      'commissionedAt' => $equipment->commissionedAt,
      'maintenanceDueStatus' => $equipment->maintenanceDueStatus,
      'tags' => array_map(static fn (array $tag): string => (string) $tag['name'], $equipment->tags),
      'createdAt' => $equipment->createdAt->format('c'),
      'updatedAt' => $equipment->updatedAt->format('c'),
      'generatedAt' => new DateTimeImmutable()->format('c'),
      'maintenanceLogs' => array_map(static fn (array $log): array => [
        'startedAt' => $log['startedAt'],
        'completedAt' => $log['completedAt'],
        'source' => $log['source'],
        'interventionNumber' => $log['interventionNumber'],
        'workItemAction' => $log['workItemAction'],
        'summary' => $log['summary'],
      ], $logs->logs),
      'maintenanceLogsTotal' => $logs->total,
      'attachments' => array_map(static fn (array $attachment): array => [
        'fileName' => $attachment['fileName'],
        'mimeType' => $attachment['mimeType'],
        'size' => $attachment['size'],
        'label' => $attachment['label'],
        'uploadedAt' => $attachment['uploadedAt'],
      ], $attachments->attachments),
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

    foreach (['installedAt', 'commissionedAt'] as $dateKey) {
      $value = $context[$dateKey] ?? null;
      $context[$dateKey] = $formatter->formatDate(is_string($value) ? $value : null);
    }

    foreach (['maintenanceLogs' => ['startedAt', 'completedAt'], 'attachments' => ['uploadedAt']] as $listKey => $dateKeys) {
      if (!isset($context[$listKey]) || !is_array($context[$listKey])) {
        continue;
      }

      $context[$listKey] = array_map(
        static function (mixed $row) use ($formatter, $dateKeys): mixed {
          if (!is_array($row)) {
            return $row;
          }

          foreach ($dateKeys as $dateKey) {
            $value = $row[$dateKey] ?? null;
            $row[$dateKey] = $formatter->formatDateTime(is_string($value) ? $value : null);
          }

          return $row;
        },
        $context[$listKey],
      );
    }

    return $context;
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\Port\Outbound\EquipmentLabelSheetPdfRendererPort;
use Equipment\Application\UseCase\Query\ExportEquipmentLabels\{ExportEquipmentLabelsQuery, ExportEquipmentLabelsResult};
use Equipment\Domain\Event\Export\EquipmentLabelsExportedEvent;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentLabelExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

use function array_map;
use function is_string;
use function sprintf;

/**
 * Controller ExportEquipmentLabelsController.
 *
 * Invokable API Platform controller serving
 * `GET /organizations/{organizationId}/equipment/labels` (wired via
 * `controller:` on a `Get` operation with `read`/`write`/`deserialize`/
 * `serialize`/`output` disabled — mirrors `ExportEquipmentsController`).
 * Thin by design: authenticate, parse the mutually exclusive `ids[]` /
 * `facilityId` selection, ask the query bus (the handler enforces the
 * organization scoping/permission check and the 500-label cap), shape the
 * rows into the Twig context — each label's `qrValue` is the equipment's
 * canonical IRI `/api/equipment/{id}`, the exact first form the frontend's
 * `InterventionDiscoveryService.normalizeScannedTarget()` accepts verbatim
 * (it also accepts a bare UUID and a full URL by pathname; the relative IRI
 * is the deterministic one, independent of any deployment host) — render
 * through the label sheet renderer port, and emit the export's own domain
 * event.
 *
 * NOT plan-gated, deliberately: QR labels are the physical half of the field
 * scan loop, which is itself ungated. The plan entitlement gate is reserved
 * for reporting deliverables (`ExportEquipmentReportController`, the safety
 * register) — gating labels would break the core scan workflow on lower
 * plans.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportEquipmentLabelsController extends AbstractController
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationDocumentBrandingPort $branding the organization document branding port
   * @param EquipmentLabelSheetPdfRendererPort $renderer the label sheet PDF renderer port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationDocumentBrandingPort $branding,
    private readonly EquipmentLabelSheetPdfRendererPort $renderer,
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
   * @return Response the PDF response
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

    $equipmentIds = $this->parseIds($request);
    $facilityId = $request->query->get('facilityId');
    $facilityId = is_string($facilityId) && '' !== $facilityId ? $facilityId : null;

    if (null !== $equipmentIds && null !== $facilityId) {
      throw new BadRequestHttpException('Provide either ids[] or facilityId, not both.');
    }

    try {
      /** @var ExportEquipmentLabelsResult $result */
      $result = $this->queryBus->ask(new ExportEquipmentLabelsQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        equipmentIds: $equipmentIds,
        facilityId: $facilityId,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapLabelException($exception);
    }

    $context = [
      'lang' => $this->branding->getDocumentBranding($organizationId)->language(),
      'labels' => array_map(
        static fn (EquipmentExportRow $row): array => [
          'qrValue' => sprintf('/api/equipment/%s', $row->id),
          'type' => $row->type,
          'subType' => $row->subType,
          'serialNumber' => $row->serialNumber,
          'facilityName' => $row->facilityName,
          'locationLabel' => $row->locationLabel,
        ],
        $result->rows,
      ),
    ];

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new EquipmentLabelsExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      selection: $result->selection,
      labelCount: $result->total,
    ));

    $fileName = sprintf('equipment-labels-%s.pdf', new DateTimeImmutable()->format('Ymd-His'));

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * Method parseIds.
   *
   * Reads the `ids[]` query parameter as a list of non-empty strings, or
   * `null` when the parameter is absent. An explicitly provided empty or
   * malformed list is a 400 — falling back to the whole park on a bad
   * parameter would silently print hundreds of unwanted labels.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return ?list<string> the equipment identifiers, or null when absent
   */
  private function parseIds(Request $request): ?array
  {
    if (!$request->query->has('ids')) {
      return null;
    }

    $raw = $request->query->all('ids');
    if ([] === $raw) {
      throw new BadRequestHttpException('ids[] must be a non-empty list of equipment identifiers.');
    }

    $ids = [];
    foreach ($raw as $value) {
      if (!is_string($value) || '' === $value) {
        throw new BadRequestHttpException('ids[] must be a non-empty list of equipment identifiers.');
      }

      $ids[] = $value;
    }

    return $ids;
  }

  /**
   * Method mapLabelException.
   *
   * Maps the (possibly bus-wrapped) domain exceptions the label handler can
   * throw to their HTTP status, following the same finder-methods idiom as
   * `ExportEquipmentsController::mapExportException()`.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception raised by the query bus
   *
   * @return Throwable the mapped exception
   */
  private function mapLabelException(Throwable $exception): Throwable
  {
    $notFound = $this->findEquipmentNotFoundException($exception);
    if ($notFound instanceof EquipmentNotFoundException) {
      return new NotFoundHttpException($notFound->getMessage(), $exception);
    }

    $accessDenied = $this->findEquipmentAccessDeniedException($exception);
    if ($accessDenied instanceof EquipmentAccessDeniedException) {
      return new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
    }

    $tooLarge = $this->findEquipmentLabelExportTooLargeException($exception);
    if ($tooLarge instanceof EquipmentLabelExportTooLargeException) {
      return new UnprocessableEntityHttpException($tooLarge->getMessage(), $exception);
    }

    $invalidArgument = $this->findInvalidArgumentException($exception);
    if ($invalidArgument instanceof InvalidArgumentException) {
      return new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }

    return $exception;
  }
  // #endregion
}

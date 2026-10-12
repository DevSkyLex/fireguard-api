<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport\{GenerateEquipmentExportCommand, GenerateEquipmentExportResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentLabelExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use LogicException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

use function base64_decode;
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
   * @param CommandBusPort $commands generates the artifact inside its owning transaction
   * @param Security $security the security service
   */
  public function __construct(
    private readonly CommandBusPort $commands,
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
    if (!$user instanceof AuthenticatedUser) {
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
      /** @var GenerateEquipmentExportResult $result */
      $result = $this->commands->dispatch(new GenerateEquipmentExportCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        format: 'labels',
        equipmentIds: $equipmentIds,
        facilityId: $facilityId,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapLabelException($exception);
    }

    $contents = base64_decode($result->contentsBase64, true);
    if (false === $contents) {
      throw new LogicException('Saved PDF artifact is invalid.');
    }

    return new Response($contents, Response::HTTP_OK, [
      'Content-Type' => $result->contentType,
      'Content-Disposition' => sprintf('attachment; filename="%s"', $result->fileName),
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
    $accessDenied = $this->findEquipmentAccessDeniedException($exception);
    $tooLarge = $this->findEquipmentLabelExportTooLargeException($exception);
    $invalidArgument = $this->findInvalidArgumentException($exception);

    return match (true) {
      $notFound instanceof EquipmentNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      $accessDenied instanceof EquipmentAccessDeniedException => new AccessDeniedHttpException($accessDenied->getMessage(), $exception),
      $tooLarge instanceof EquipmentLabelExportTooLargeException => new UnprocessableEntityHttpException($tooLarge->getMessage(), $exception),
      $invalidArgument instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
  }
  // #endregion
}

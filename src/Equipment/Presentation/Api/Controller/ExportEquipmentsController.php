<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Query\ExportEquipments\{ExportEquipmentsQuery, ExportEquipmentsResult};
use Equipment\Domain\Event\Export\EquipmentsExportedEvent;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Service\EquipmentCsvWriter;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;

use function fclose;
use function fopen;
use function is_string;
use function sprintf;

/**
 * Controller ExportEquipmentsController.
 *
 * Invokable API Platform controller serving
 * `GET /organizations/{organizationId}/equipment/export` (wired via
 * `controller:` on a `Get` operation on `EquipmentExportResource`, with
 * `read`/`write`/`serialize`/`deserialize`/`output` disabled) — mirrors
 * `Intervention\...\ExportInterventionsController`. Kept thin: authenticate,
 * resolve the `organizationId` URI variable exactly like
 * `DownloadEquipmentAttachmentController` does, ask the query bus (the
 * handler enforces the organization scoping/permission check and the
 * export's row cap), stream the CSV, and emit the export's own domain event.
 * Resource-level `is_granted('ROLE_USER')` is only the coarse gate — the true
 * `organization.equipment.read` entitlement and the tenant scoping are
 * resolved inside `ExportEquipmentsHandler`, exactly like every other
 * Equipment endpoint resolves it.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportEquipmentsController extends AbstractController
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   * @param EquipmentCsvWriter $csvWriter the CSV row writer
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
    private readonly EquipmentCsvWriter $csvWriter,
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
   * @return StreamedResponse the streamed CSV response
   */
  public function __invoke(Request $request): StreamedResponse
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    try {
      /** @var ExportEquipmentsResult $result */
      $result = $this->queryBus->ask(new ExportEquipmentsQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapExportException($exception);
    }

    $this->eventDispatcher->dispatch(new EquipmentsExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      format: 'csv',
      rowCount: $result->total,
    ));

    $fileName = sprintf('equipments-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

    $response = new StreamedResponse(function () use ($result): void {
      $handle = fopen('php://output', 'wb');
      if (false === $handle) {
        return;
      }

      $this->csvWriter->write($result->rows, $handle);
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }

  /**
   * Method mapExportException.
   *
   * Maps the (possibly bus-wrapped) domain exceptions the export handler can
   * throw to their HTTP status, following the same finder-methods idiom
   * `EquipmentExceptionUnwrapperTrait` already offers the rest of the module,
   * rather than the generic recursive-match mapper `Intervention`'s export
   * controller uses.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception raised by the query bus
   *
   * @return Throwable the mapped exception
   */
  private function mapExportException(Throwable $exception): Throwable
  {
    // The finders walk the whole wrapping chain themselves (the exception
    // itself, `getPrevious()`, and Messenger's wrapped exceptions), so no
    // wrapper-type gate is needed — a plain `RuntimeException` wrapper is
    // unwrapped exactly like a `MessengerRuntimeException`, mirroring
    // `Intervention`'s `mapWorkflowException`.
    $notFound = $this->findEquipmentNotFoundException($exception);
    if ($notFound instanceof EquipmentNotFoundException) {
      return new NotFoundHttpException($notFound->getMessage(), $exception);
    }

    $accessDenied = $this->findEquipmentAccessDeniedException($exception);
    if ($accessDenied instanceof EquipmentAccessDeniedException) {
      return new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
    }

    $tooLarge = $this->findEquipmentExportTooLargeException($exception);
    if ($tooLarge instanceof EquipmentExportTooLargeException) {
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

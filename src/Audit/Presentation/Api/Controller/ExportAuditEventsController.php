<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Controller;

use Audit\Application\UseCase\Query\ExportAuditEvents\{ExportAuditEventsQuery, ExportAuditEventsResult};
use Audit\Domain\Event\AuditEventsExportedEvent;
use Audit\Presentation\Api\Service\{AuditEventCsvWriter, AuditEventExportCriteriaFactory};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Shared\Application\Exception\MessengerExceptionUnwrapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

use function fclose;
use function fopen;
use function sprintf;

/**
 * Controller ExportAuditEventsController.
 *
 * Invokable API Platform controller (wired via `controller:` on the export
 * `Get` operation, `read`/`write`/`serialize`/`deserialize`/`output`
 * disabled — the same pattern `Compliance\Presentation\Api\Controller\ExportSafetyRegisterController`
 * uses for its non-resource binary `Response`). Kept thin: authenticate,
 * parse the same filters the list endpoint supports, ask the query bus (the
 * handler enforces the export's row cap), stream the CSV, and emit the
 * export's own audit event.
 *
 * The `audit.export` permission is checked **here**, not by the operation's
 * `security:` metadata. API Platform evaluates `security:` inside the state
 * provider chain ({@see \ApiPlatform\Symfony\Security\State\AccessCheckerProvider}),
 * which this operation never enters: it declares `read: false` and a custom
 * `controller:`, so the expression is documentation-only and every
 * authenticated caller would otherwise stream the whole ledger. The metadata
 * is kept for the OpenAPI contract; this check is what enforces it.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportAuditEventsController extends AbstractController
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constants
  /**
   * Constant EXPORT_PERMISSION.
   *
   * The RBAC permission the export requires — the same name the operation's
   * `security:` metadata and the OpenAPI 403 description carry.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string EXPORT_PERMISSION = 'audit.export';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   * @param AuditEventExportCriteriaFactory $criteriaFactory the request-to-criteria factory
   * @param AuditEventCsvWriter $csvWriter the CSV row writer
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
    private readonly AuditEventExportCriteriaFactory $criteriaFactory,
    private readonly AuditEventCsvWriter $csvWriter,
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

    if (!$this->security->isGranted(self::EXPORT_PERMISSION)) {
      throw new AccessDeniedHttpException('Insufficient permissions (requires audit.export).');
    }

    $criteria = $this->criteriaFactory->fromRequest($request);

    // The cap failure now reaches the client as the documented 422 without a
    // catch here: BusFailureUnwrappingSubscriber opens the envelope the comment
    // this replaces described, and `exception_to_status` carries the status.
    /** @var ExportAuditEventsResult $result */
    $result = $this->queryBus->ask(new ExportAuditEventsQuery(criteria: $criteria));

    $this->eventDispatcher->dispatch(new AuditEventsExportedEvent(
      actorUserId: $user->getId(),
      tenantId: $criteria->tenantId,
      format: 'csv',
      rowCount: $result->total,
      filterKeys: $this->criteriaFactory->appliedFilterKeys($criteria),
    ));

    $fileName = sprintf('audit-events-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

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
  // #endregion
}

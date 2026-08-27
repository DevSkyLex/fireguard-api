<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\{ExportMaintenanceSchedulesQuery, ExportMaintenanceSchedulesResult};
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Maintenance\Presentation\Api\Service\{MaintenanceScheduleCsvWriter, MaintenanceScheduleExportCriteriaFactory};
use Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function fclose;
use function fopen;
use function is_string;
use function sprintf;

/**
 * Controller ExportMaintenanceSchedulesController.
 *
 * Invokable API Platform controller (wired via `controller:` on the export
 * `Get` operation, `read`/`write`/`serialize`/`deserialize`/`output`
 * disabled — mirrors `Intervention\...\ExportInterventionsController`). Kept
 * thin: authenticate, resolve the required `organization` query parameter
 * exactly like `MaintenanceScheduleProvider` does for the list endpoint,
 * parse the cheap filter subset, ask the query bus (the handler enforces the
 * organization scoping/permission check and the export's row cap), stream
 * the CSV, and emit the export's own audit event. Resource-level
 * `is_granted('ROLE_USER')` is only the coarse gate — the true
 * `organization.maintenance.read` entitlement and the tenant scoping are
 * resolved inside `ExportMaintenanceSchedulesHandler`, exactly like the list
 * endpoint.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportMaintenanceSchedulesController extends AbstractController
{
  use MaintenanceExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   * @param MaintenanceScheduleExportCriteriaFactory $criteriaFactory the request-to-filters factory
   * @param MaintenanceScheduleCsvWriter $csvWriter the CSV row writer
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
    private readonly MaintenanceScheduleExportCriteriaFactory $criteriaFactory,
    private readonly MaintenanceScheduleCsvWriter $csvWriter,
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

    $organization = $request->query->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $organizationId = ResourceIriParser::id($organization, 'organizations');

    $filters = $this->criteriaFactory->fromRequest($request);

    try {
      /** @var ExportMaintenanceSchedulesResult $result */
      $result = $this->queryBus->ask(new ExportMaintenanceSchedulesQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        facilityId: $filters['facilityId'],
        equipmentType: $filters['equipmentType'],
        dueStatus: $filters['dueStatus'],
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapMaintenanceException($exception);
    }

    $this->eventDispatcher->dispatch(new MaintenanceSchedulesExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      format: 'csv',
      rowCount: $result->total,
      filterKeys: $this->criteriaFactory->appliedFilterKeys($filters),
    ));

    $fileName = sprintf('maintenance-schedules-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

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

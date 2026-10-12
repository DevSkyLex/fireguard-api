<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport\{PrepareMaintenanceScheduleExportCommand, PrepareMaintenanceScheduleExportResult};
use Maintenance\Presentation\Api\Service\MaintenanceScheduleExportCriteriaFactory;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportMaintenanceSchedulesController extends AbstractController
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the query bus
   * @param Security $security the security service
   * @param MaintenanceScheduleExportCriteriaFactory $criteriaFactory the request-to-filters factory
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly Security $security,
    private readonly MaintenanceScheduleExportCriteriaFactory $criteriaFactory,
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
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organization = $request->query->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $organizationId = ResourceIriParser::id($organization, 'organizations');

    $filters = $this->criteriaFactory->fromRequest($request);

    /** @var PrepareMaintenanceScheduleExportResult $result */
    $result = $this->commandBus->dispatch(new PrepareMaintenanceScheduleExportCommand(
      userId: $user->getId(),
      organizationId: $organizationId,
      facilityId: $filters['facilityId'],
      equipmentType: $filters['equipmentType'],
      dueStatus: $filters['dueStatus'],
      dueBefore: $filters['dueBefore'],
    ));

    $response = new StreamedResponse(static function () use ($result): void {
      echo $result->contents();
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }
  // #endregion
}

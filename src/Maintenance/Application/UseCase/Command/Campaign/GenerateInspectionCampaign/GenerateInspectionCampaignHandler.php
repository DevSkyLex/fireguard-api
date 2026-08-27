<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, InterventionDraftWorkItem};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Domain\Exception\{MaintenanceNotFoundException, MaintenanceValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * UseCase GenerateInspectionCampaignHandler.
 *
 * Selects `due_soon`/`overdue` maintenance schedules matching the given
 * filters and materializes them as one planned inspection work item per
 * equipment inside a freshly created intervention draft, routed through
 * `InterventionDraftFactoryPort` (the same programmatic draft-creation path
 * other automations use) with `origin: 'maintenance:campaign'`.
 *
 * Authorization gates on `OrganizationAuthorizationPort::isMemberOf()` first
 * — a caller with no active membership in the organization gets the same 404
 * an unknown organization id produces, never a 403 that would confirm the
 * organization exists — then requires BOTH `organization.maintenance.manage`
 * (the maintenance-side "prepare a campaign" capability) and
 * `organization.interventions.plan` (the draft factory itself does not
 * authorize — callers must).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateInspectionCampaignHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRepositoryPort $schedules the schedule repository port
   * @param InterventionDraftFactoryPort $draftFactory the cross-module intervention draft factory port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private InterventionDraftFactoryPort $draftFactory,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GenerateInspectionCampaignCommand $command the command value
   *
   * @return GenerateInspectionCampaignResult the command result
   */
  public function __invoke(GenerateInspectionCampaignCommand $command): GenerateInspectionCampaignResult
  {
    if (!$this->authorization->isMemberOf($command->actorUserId, $command->organizationId)) {
      throw MaintenanceNotFoundException::forOrganizationScope($command->organizationId);
    }

    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.maintenance.manage',
      'organization.interventions.plan',
    ]);

    $dueSchedules = $this->schedules->listDueForCampaign(
      $command->organizationId,
      $command->facilityId,
      $command->equipmentType,
      $command->dueBefore,
    );

    if ([] === $dueSchedules) {
      throw new MaintenanceValidationException('No due maintenance schedules match the given filters.');
    }

    $workItems = array_map(
      static fn ($schedule): InterventionDraftWorkItem => new InterventionDraftWorkItem(
        action: 'inspection',
        target: json_encode(['equipmentId' => $schedule->equipmentId], JSON_THROW_ON_ERROR),
        required: true,
      ),
      $dueSchedules,
    );

    $draft = $this->draftFactory->create(new CreateInterventionDraftRequest(
      organizationId: $command->organizationId,
      type: 'inspection_campaign',
      name: $command->name,
      origin: 'maintenance:campaign',
      workItems: $workItems,
      actorUserId: $command->actorUserId,
    ));

    $this->eventDispatcher->dispatch(new MaintenanceCampaignGeneratedEvent(
      organizationId: $command->organizationId,
      interventionId: $draft->interventionId,
      workItemsCount: $draft->workItemsCount,
      actorUserId: $command->actorUserId,
    ));

    return new GenerateInspectionCampaignResult(
      interventionId: $draft->interventionId,
      number: $draft->number,
      workItemsCount: $draft->workItemsCount,
    );
  }
}

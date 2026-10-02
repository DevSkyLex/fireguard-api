<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Draft\{
  CreateInterventionDraftRequest,
  CreatedInterventionDraft,
  InterventionDraftWorkItem
};
use Intervention\Application\Contract\Workflow\InterventionWorkflowMutation;
use Intervention\Application\Exception\InterventionDraftCreationException;
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Outbound\TransactionManagerPort;

use function count;
use function is_int;
use function is_string;

/**
 * Service InterventionDraftFactory.
 *
 * The single programmatic entry point for creating intervention drafts on
 * behalf of other modules. Every creation routes through the workflow gateway
 * — the exact same path the HTTP API uses — so sequential numbering, the
 * created system activity and all domain invariants are enforced identically.
 *
 * The acting user (when any) is recorded as the activity actor; a platform
 * actor resolves to no member and the activity is attributed to the system,
 * mirroring how CLI/async actions already behave elsewhere.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftFactory implements InterventionDraftFactoryPort
{
  // #region Constants
  /**
   * The user identifier recorded when the platform itself acts. It never
   * matches an organization member, so the activity actor resolves to system.
   */
  private const string SYSTEM_ACTOR = 'system';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionDraftFactory class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the intervention workflow gateway
   * @param LoggerInterface $logger the logger
   * @param TransactionManagerPort $transactions owns the entire draft on main
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private LoggerInterface $logger,
    private TransactionManagerPort $transactions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates an intervention draft and seeds its planned work items. A failure
   * while seeding work items rolls back the draft, numbering, activities and effects.
   *
   * @since 1.0.0
   *
   * @param CreateInterventionDraftRequest $request the creation request
   *
   * @return CreatedInterventionDraft the created draft summary
   */
  public function create(CreateInterventionDraftRequest $request): CreatedInterventionDraft
  {
    return $this->transactions->transactional(fn (): CreatedInterventionDraft => $this->createAtomically($request));
  }

  /**
   * Method createAtomically
   *
   * Creates the complete draft within the owning main transaction.
   *
   * @access private
   *
   * @param CreateInterventionDraftRequest $request the complete draft specification
   *
   * @return CreatedInterventionDraft the committed draft summary
   */
  private function createAtomically(CreateInterventionDraftRequest $request): CreatedInterventionDraft
  {
    $actor = $request->actorUserId ?? self::SYSTEM_ACTOR;

    $view = $this->gateway->mutate(new InterventionWorkflowMutation(
      resource: 'intervention',
      action: 'create',
      userId: $actor,
      id: null,
      payload: [
        'organizationId' => $request->organizationId,
        'type' => $request->type,
        'name' => $request->name,
        'description' => $request->description,
        'priority' => $request->priority,
        'siteId' => $request->siteId,
        'responsibleId' => $request->responsibleId,
        'participants' => $request->participants,
        'plannedStartAt' => $request->plannedStartAt?->format('c'),
        'dueAt' => $request->dueAt?->format('c'),
        'labelIds' => $request->labelIds,
      ],
    ));

    $interventionId = $view?->data['id'] ?? null;
    $number = $view?->data['number'] ?? null;
    if (!is_string($interventionId) || !is_int($number)) {
      throw new InterventionDraftCreationException('The workflow gateway returned no intervention view on creation.');
    }

    foreach ($request->workItems as $workItem) {
      $this->createWorkItem($interventionId, $actor, $workItem);
    }

    $this->logger->info('Intervention draft created programmatically.', [
      'interventionId' => $interventionId,
      'organizationId' => $request->organizationId,
      'origin' => $request->origin,
      'workItems' => count($request->workItems),
    ]);

    return new CreatedInterventionDraft(
      interventionId: $interventionId,
      number: $number,
      workItemsCount: count($request->workItems),
    );
  }

  /**
   * Method createWorkItem.
   *
   * Seeds one planned work item into the freshly created draft.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention identifier
   * @param string $actor the acting user identifier
   * @param InterventionDraftWorkItem $workItem the work item to seed
   *
   * @return void completes without returning a value
   */
  private function createWorkItem(string $interventionId, string $actor, InterventionDraftWorkItem $workItem): void
  {
    $this->gateway->mutate(new InterventionWorkflowMutation(
      resource: 'work_item',
      action: 'create',
      userId: $actor,
      id: null,
      payload: [
        'interventionId' => $interventionId,
        'action' => $workItem->action,
        'target' => $workItem->target,
        'resultResource' => $workItem->resultResource,
        'assigneeId' => $workItem->assigneeId,
        'source' => 'planned',
        'required' => $workItem->required,
        'estimatedMinutes' => $workItem->estimatedMinutes,
      ],
    ));
  }
  // #endregion
}

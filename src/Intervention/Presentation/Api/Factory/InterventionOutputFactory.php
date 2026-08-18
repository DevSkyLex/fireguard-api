<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Service\{InterventionActionPolicy, InterventionAllowedActions};
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionStatus;
use Intervention\Presentation\Api\Dto\Output\{InterventionAllowedActionsOutput, InterventionOutput};
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;
use LogicException;
use Shared\Presentation\Api\Http\ResourceIriParser;

use function array_map;

/**
 * Factory InterventionOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOutputFactory
{
  use InterventionWorkflowViewDataTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionOutputFactory class.
   *
   * @since 1.0.0
   *
   * @param InterventionTransitionPolicy $transitionPolicy the transition policy value
   * @param ?InterventionActionPolicy $actionPolicy the shared action policy, required by
   *                                                {@see self::fromViewForCaller()} only —
   *                                                optional so a caller-agnostic write-path
   *                                                response (only ever built via
   *                                                {@see self::fromView()}) does not need one
   */
  public function __construct(
    private readonly InterventionTransitionPolicy $transitionPolicy,
    private readonly ?InterventionActionPolicy $actionPolicy = null,
  ) {
  }

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   *
   * @return InterventionOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view): InterventionOutput
  {
    $data = $view->data;
    $output = new InterventionOutput();
    $output->id = $this->string($data, 'id');
    $output->organization = $this->string($data, 'organization');
    $output->number = $this->integer($data, 'number');
    $output->type = $this->string($data, 'type');
    $output->name = $this->string($data, 'name');
    $output->description = $this->nullableString($data, 'description');
    $output->status = $this->string($data, 'status');
    $output->site = $this->nullableString($data, 'site');
    $output->responsible = $this->nullableString($data, 'responsible');
    $output->participants = $this->stringList($data, 'participants');
    $output->priority = $this->string($data, 'priority');
    $output->plannedStartAt = $this->nullableString($data, 'plannedStartAt');
    $output->dueAt = $this->nullableString($data, 'dueAt');
    $output->reviewNote = $this->nullableString($data, 'reviewNote');
    $output->revision = $this->integer($data, 'revision');
    // Absent from the list view's data by design — see InterventionOutput.
    $output->recurrence = $this->nullableString($data, 'recurrence');
    $output->allowedTransitions = $this->allowedTransitionsFor($output->status);
    $output->facilitiesCount = $this->integer($data, 'facilitiesCount');
    $output->equipmentCount = $this->integer($data, 'equipmentCount');
    $output->inspectionsCount = $this->integer($data, 'inspectionsCount');
    $output->blockersCount = $this->integer($data, 'blockersCount');
    $output->workItemsCount = $this->integer($data, 'workItemsCount');
    $output->completedWorkItemsCount = $this->integer($data, 'completedWorkItemsCount');
    $output->proposedChangesCount = $this->integer($data, 'proposedChangesCount');
    $output->commentsCount = $this->integer($data, 'commentsCount');
    $output->hasSignature = $this->boolean($data, 'hasSignature');
    $output->labels = $this->labelList($data);
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }

  /**
   * Method fromViewForCaller.
   *
   * The caller-aware variant of {@see self::fromView()} — used on the item
   * and collection read paths and on every mutation response returning the
   * refreshed intervention: additionally populates `allowedActions` by
   * asking the shared
   * {@see InterventionActionPolicy} — the same policy
   * `MutateInterventionWorkflowHandler` consults to enforce a mutation —
   * what THIS caller may do to THIS intervention right now. Computed
   * entirely from the already-loaded view data and the caller's organization
   * permissions (memoized per request): no additional query per row, so
   * this is safe to call for every item of a collection.
   *
   * @since 1.3.0
   *
   * @param InterventionWorkflowView $view the view value
   * @param string $userId the caller's user id
   *
   * @return InterventionOutput the from view result, with `allowedActions` populated
   */
  public function fromViewForCaller(InterventionWorkflowView $view, string $userId): InterventionOutput
  {
    if (!$this->actionPolicy instanceof InterventionActionPolicy) {
      throw new LogicException('InterventionOutputFactory was not wired with an InterventionActionPolicy.');
    }

    $output = $this->fromView($view);
    $context = new InterventionWorkflowContext(
      interventionId: $output->id,
      organizationId: $view->organizationId,
      status: $output->status,
      responsibleId: null === $output->responsible ? null : ResourceIriParser::memberId($output->responsible),
      participants: array_map(ResourceIriParser::memberId(...), $output->participants),
    );
    $output->allowedActions = $this->mapAllowedActions($this->actionPolicy->allowedActions($context, $userId));

    return $output;
  }

  /**
   * Method mapAllowedActions.
   *
   * Maps the Application-layer {@see InterventionAllowedActions} value onto
   * the Presentation `InterventionAllowedActionsOutput` DTO, field by field.
   *
   * @since 1.3.0
   *
   * @param InterventionAllowedActions $actions the allowed-actions value
   *
   * @return InterventionAllowedActionsOutput the mapped output
   */
  private function mapAllowedActions(InterventionAllowedActions $actions): InterventionAllowedActionsOutput
  {
    $output = new InterventionAllowedActionsOutput();
    $output->canEditDetails = $actions->canEditDetails;
    $output->canEditSite = $actions->canEditSite;
    $output->canEditResponsible = $actions->canEditResponsible;
    $output->canEditPlanning = $actions->canEditPlanning;
    $output->canMutateWorkItems = $actions->canMutateWorkItems;
    $output->canMutateChanges = $actions->canMutateChanges;
    $output->canAssignTeam = $actions->canAssignTeam;
    $output->canManageAttachments = $actions->canManageAttachments;
    $output->canSubmit = $actions->canSubmit;
    $output->canWithdraw = $actions->canWithdraw;
    $output->canDelete = $actions->canDelete;
    $output->canPublish = $actions->canPublish;

    return $output;
  }

  /**
   * Method allowedTransitionsFor.
   *
   * Resolves the workflow-legal next statuses for a raw status string, as the
   * lower-cased backend enum values the client already speaks. Unknown statuses
   * yield an empty list rather than throwing, keeping the read path resilient.
   *
   * @since 1.0.0
   *
   * @param string $status the current status value
   *
   * @return list<string> the allowed next status values
   */
  private function allowedTransitionsFor(string $status): array
  {
    $current = InterventionStatus::tryFrom($status);
    if (!$current instanceof InterventionStatus) {
      return [];
    }

    return array_map(
      static fn (InterventionStatus $next): string => $next->value,
      $this->transitionPolicy->allowedFrom($current),
    );
  }

  /**
   * Method labelList.
   *
   * Normalizes the `labels` view data into the embedded `{id, name, color}`
   * shape the client uses to render chips without extra requests.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the view data value
   *
   * @return list<array{id: string, name: string, color: string}>
   */
  private function labelList(array $data): array
  {
    return array_map(
      fn (array $label): array => [
        'id' => $this->string($label, 'id'),
        'name' => $this->string($label, 'name'),
        'color' => $this->string($label, 'color'),
      ],
      $this->objectList($data, 'labels'),
    );
  }
}

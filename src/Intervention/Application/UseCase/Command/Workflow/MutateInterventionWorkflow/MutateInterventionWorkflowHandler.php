<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowMutation};
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function array_key_exists;
use function array_unique;
use function array_values;
use function in_array;
use function is_string;

/**
 * UseCase MutateInterventionWorkflowHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MutateInterventionWorkflowHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MutateInterventionWorkflowHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowGatewayPort $gateway the gateway value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param InterventionMemberPolicy $memberPolicy the intervention member policy
   */
  public function __construct(
    private InterventionWorkflowGatewayPort $gateway,
    private OrganizationAuthorizationPort $authorization,
    private InterventionMemberPolicy $memberPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param MutateInterventionWorkflowCommand $command the command value
   *
   * @return MutateInterventionWorkflowResult the   invoke result
   */
  public function __invoke(MutateInterventionWorkflowCommand $command): MutateInterventionWorkflowResult
  {
    $context = $this->context($command);
    $permissions = $this->permissions($command, $context);
    foreach ($permissions as $permission) {
      $decision = $this->authorization->resolveAccess($command->userId, $context->organizationId, $permission);
      if ($decision->isOutsideScope()) {
        throw $this->outsideScope($command, $context);
      }
      if (!$decision->isGranted()) {
        throw new InterventionAccessDeniedException('Missing ' . $permission . ' permission.');
      }
    }
    if (in_array('organization.interventions.execute', $permissions, true)) {
      $this->memberPolicy->assertCanExecuteIntervention(
        $context->organizationId,
        $command->userId,
        $context->responsibleId,
        $context->participants,
      );
    }

    return new MutateInterventionWorkflowResult($this->gateway->mutate(new InterventionWorkflowMutation(
      resource: $command->resource,
      action: $command->action,
      userId: $command->userId,
      id: $command->id,
      payload: $command->payload,
      expectedRevision: $command->expectedRevision,
      createOnly: $command->createOnly,
    )));
  }

  /**
   * Method context.
   *
   * Executes the context operation.
   *
   * @since 1.0.0
   *
   * @param MutateInterventionWorkflowCommand $command the command value
   *
   * @return InterventionWorkflowContext the context result
   */
  private function context(MutateInterventionWorkflowCommand $command): InterventionWorkflowContext
  {
    if ('create' === $command->action && 'intervention' === $command->resource) {
      $organizationId = $command->payload['organizationId'] ?? null;
      if (!is_string($organizationId) || '' === $organizationId) {
        throw InterventionNotFoundException::withId('organization');
      }

      return new InterventionWorkflowContext(
        interventionId: $command->id ?? '',
        organizationId: $organizationId,
        status: 'draft',
        responsibleId: is_string($command->payload['responsibleId'] ?? null) ? $command->payload['responsibleId'] : null,
      );
    }

    if ('create' === $command->action) {
      $interventionId = $command->payload['interventionId'] ?? null;
      $context = is_string($interventionId) ? $this->gateway->interventionContext($interventionId) : null;
    } else {
      $context = null === $command->id ? null : $this->gateway->resourceContext($command->resource, $command->id);
    }
    if (!$context instanceof InterventionWorkflowContext) {
      throw InterventionNotFoundException::withId($command->id ?? 'unknown');
    }

    return $context;
  }

  /**
   * Method outsideScope.
   *
   * The not-found exception a caller with no active membership in the owning
   * organization must receive.
   *
   * Deliberately the same exception {@see self::context()} raises on the same
   * branch for an unknown identifier: an outsider who could tell the two
   * apart would be able to confirm which identifiers are real.
   *
   * @since 1.1.0
   *
   * @param MutateInterventionWorkflowCommand $command the command value
   * @param InterventionWorkflowContext $context the resolved context
   *
   * @return InterventionNotFoundException the exception to throw
   */
  private function outsideScope(MutateInterventionWorkflowCommand $command, InterventionWorkflowContext $context): InterventionNotFoundException
  {
    return 'create' === $command->action && 'intervention' === $command->resource
      ? InterventionNotFoundException::forOrganizationScope($context->organizationId)
      : InterventionNotFoundException::withId($command->id ?? 'unknown');
  }

  /**
   * Method permissions.
   *
   * Every permission the mutation requires — ALL of them (AND). A payload
   * touching planning fields on a non-draft intervention is a replan and
   * requires `plan`: alone when the payload is planning-only (a planner who is
   * neither responsible nor participant may reschedule), on top of the base
   * permission when the payload also transitions or edits other fields. The
   * member guard in `__invoke` keys off `execute` being required.
   *
   * @since 1.1.0
   *
   * @param MutateInterventionWorkflowCommand $command the command value
   * @param InterventionWorkflowContext $context the context value
   *
   * @return non-empty-list<string> the required permissions
   */
  private function permissions(MutateInterventionWorkflowCommand $command, InterventionWorkflowContext $context): array
  {
    $base = $this->permission($command, $context);
    if ('intervention' !== $command->resource || 'create' === $command->action || 'draft' === $context->status) {
      return [$base];
    }

    $touchesPlanning = false;
    foreach (['siteId', 'responsibleId', 'participants', 'priority', 'plannedStartAt', 'dueAt'] as $field) {
      if (array_key_exists($field, $command->payload)) {
        $touchesPlanning = true;

        break;
      }
    }
    if (!$touchesPlanning) {
      return [$base];
    }

    $editsBeyondPlanning = false;
    foreach (['status', 'name', 'description', 'reviewNote', 'labelIds'] as $field) {
      if (array_key_exists($field, $command->payload)) {
        $editsBeyondPlanning = true;

        break;
      }
    }

    return $editsBeyondPlanning
      ? array_values(array_unique([$base, 'organization.interventions.plan']))
      : ['organization.interventions.plan'];
  }

  /**
   * Method permission.
   *
   * Executes the permission operation.
   *
   * @since 1.0.0
   *
   * @param MutateInterventionWorkflowCommand $command the command value
   * @param InterventionWorkflowContext $context the context value
   *
   * @return string the permission result
   */
  private function permission(MutateInterventionWorkflowCommand $command, InterventionWorkflowContext $context): string
  {
    if ('intervention' === $command->resource) {
      if ('create' === $command->action) {
        return 'organization.interventions.plan';
      }
      $status = $command->payload['status'] ?? null;
      if (is_string($status)) {
        return match ($status) {
          'planned' => 'organization.interventions.plan',
          'in_progress', 'submitted' => 'organization.interventions.execute',
          'abandoned' => match ($context->status) {
            'draft' => 'organization.interventions.plan',
            'changes_requested' => 'organization.interventions.review',
            default => 'organization.interventions.execute',
          },
          'changes_requested' => 'organization.interventions.review',
          default => 'organization.interventions.plan',
        };
      }

      return 'draft' === $context->status ? 'organization.interventions.plan' : 'organization.interventions.execute';
    }

    if ('work_item' === $command->resource) {
      return 'draft' === $context->status ? 'organization.interventions.plan' : 'organization.interventions.execute';
    }

    return 'submitted' === $context->status && 'create' !== $command->action
      ? 'organization.interventions.review'
      : 'organization.interventions.execute';
  }
}

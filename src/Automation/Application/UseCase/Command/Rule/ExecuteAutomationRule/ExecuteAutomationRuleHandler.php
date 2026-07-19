<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Contract\Trigger\AutomationTriggers;
use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRunPort};
use Automation\Domain\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use DateInterval;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, InterventionDraftWorkItem};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Psr\Log\LoggerInterface;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Throwable;

use function array_filter;
use function is_string;
use function json_encode;
use function mb_substr;

use const JSON_THROW_ON_ERROR;

/**
 * UseCase ExecuteAutomationRuleHandler.
 *
 * The single execution path for every automation rule. For the one rule
 * this lot implements (`auto_create_intervention_on_critical_nc`):
 *
 * 1. Idempotence — the run is reserved FIRST
 *    ({@see AutomationRunPort::reserveRun()}); a reservation miss (already
 *    claimed by a previous attempt) is a silent no-op.
 * 2. The organization's automation policy is read fresh (never trusted from
 *    the trigger): when the rule is toggled off, the run is marked
 *    `skipped` and nothing else happens.
 * 3. The action — a corrective intervention draft — is built through
 *    {@see InterventionDraftFactoryPort}: a system actor (`actorUserId:
 *    null`), `origin: 'automation:<rule key>'`, type `inspection_campaign`
 *    with one required `inspection` work item, priority `urgent`, and
 *    `dueAt` derived from `now + nonConformitySlaDays[severity]` days.
 * 4. On success the run is marked `succeeded` with the created intervention
 *    id and `automation.rule_executed` is dispatched. On failure the run is
 *    marked `failed` with the error and `automation.rule_failed` is
 *    dispatched — the failure is deliberately swallowed (logged, not
 *    rethrown): the run row already guards against a Messenger retry
 *    producing a duplicate draft, so rethrowing would only risk retries
 *    that can never succeed differently.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExecuteAutomationRuleHandler implements CommandHandler
{
  // #region Constants
  /**
   * Fallback SLA (days) used when the policy carries no entry for the
   * triggering severity — should not happen in practice, since
   * `OrganizationComplianceSettings::effectiveNonConformitySlaDays()`
   * always overlays the full catalog defaults.
   */
  private const int FALLBACK_SLA_DAYS = 1;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AutomationRunPort $runs the automation run port
   * @param AutomationPolicyPort $policy the automation policy port
   * @param InterventionDraftFactoryPort $draftFactory the cross-module intervention draft factory port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private AutomationRunPort $runs,
    private AutomationPolicyPort $policy,
    private InterventionDraftFactoryPort $draftFactory,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExecuteAutomationRuleCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(ExecuteAutomationRuleCommand $command): VoidResult
  {
    $runId = $this->runs->reserveRun($command->ruleKey, $command->organizationId, $command->subjectId);
    if (null === $runId) {
      // Already claimed by a previous attempt: skip entirely.
      return new VoidResult();
    }

    $policy = $this->policy->policyFor($command->organizationId);

    if (!$this->isEnabled($command->ruleKey, $policy)) {
      $this->runs->markSkipped($runId);

      return new VoidResult();
    }

    try {
      $interventionId = $this->execute($command, $policy);
    } catch (Throwable $exception) {
      $this->runs->markFailed($runId, $exception->getMessage());
      $this->logger->error('Automation rule execution failed', [
        'rule_key' => $command->ruleKey,
        'organization_id' => $command->organizationId,
        'subject_id' => $command->subjectId,
        'error' => $exception->getMessage(),
      ]);
      $this->eventDispatcher->dispatch(new AutomationRuleFailedEvent(
        ruleKey: $command->ruleKey,
        organizationId: $command->organizationId,
        subjectId: $command->subjectId,
        error: $exception->getMessage(),
      ));

      return new VoidResult();
    }

    $this->runs->markSucceeded($runId, $interventionId);
    $this->eventDispatcher->dispatch(new AutomationRuleExecutedEvent(
      ruleKey: $command->ruleKey,
      organizationId: $command->organizationId,
      subjectId: $command->subjectId,
      interventionId: $interventionId,
    ));

    return new VoidResult();
  }

  /**
   * Method isEnabled.
   *
   * Re-checks the policy for the given rule key rather than trusting the
   * trigger subscriber's own gate — the authoritative toggle check always
   * lives here, so a future rule (or a policy that grows beyond a single
   * boolean, e.g. per-severity) only needs updating in this one place.
   *
   * @since 1.0.0
   *
   * @param string $ruleKey the automation rule key value
   * @param AutomationPolicy $policy the resolved automation policy value
   *
   * @return bool true when the rule is enabled for the organization
   */
  private function isEnabled(string $ruleKey, AutomationPolicy $policy): bool
  {
    return match ($ruleKey) {
      AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC => $policy->autoCreateInterventionOnCriticalNc,
      default => false,
    };
  }

  /**
   * Method execute.
   *
   * Builds and creates the corrective intervention draft for
   * `auto_create_intervention_on_critical_nc`. The only rule implemented
   * today; `isEnabled()` already rejected every other rule key before this
   * is reached.
   *
   * @since 1.0.0
   *
   * @param ExecuteAutomationRuleCommand $command the command value
   * @param AutomationPolicy $policy the resolved automation policy value
   *
   * @return string the created intervention identifier
   */
  private function execute(ExecuteAutomationRuleCommand $command, AutomationPolicy $policy): string
  {
    $severity = $this->payloadString($command, 'severity') ?? 'critical';
    $nonConformityId = $this->payloadString($command, 'nonConformityId') ?? $command->subjectId;
    $inspectionId = $this->payloadString($command, 'inspectionId');
    $slaDays = $policy->nonConformitySlaDays[$severity] ?? self::FALLBACK_SLA_DAYS;

    $target = json_encode(array_filter([
      'nonConformityId' => $nonConformityId,
      'inspectionId' => $inspectionId,
    ]), JSON_THROW_ON_ERROR);

    $draft = $this->draftFactory->create(new CreateInterventionDraftRequest(
      organizationId: $command->organizationId,
      type: 'inspection_campaign',
      name: 'Corrective — non-conformity ' . mb_substr($nonConformityId, 0, 8),
      origin: 'automation:' . $command->ruleKey,
      priority: 'urgent',
      dueAt: $this->clock->now()->add(new DateInterval('P' . $slaDays . 'D')),
      workItems: [
        new InterventionDraftWorkItem(action: 'inspection', target: $target, required: true),
      ],
      actorUserId: null,
    ));

    return $draft->interventionId;
  }

  /**
   * Method payloadString.
   *
   * @since 1.0.0
   *
   * @param ExecuteAutomationRuleCommand $command the command value
   * @param string $key the payload key value
   *
   * @return ?string the string value at the given payload key, or null
   */
  private function payloadString(ExecuteAutomationRuleCommand $command, string $key): ?string
  {
    $value = $command->triggerPayload[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }
  // #endregion
}

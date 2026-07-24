<?php

declare(strict_types=1);

namespace Automation\Application\Contract\Trigger;

/**
 * Contract AutomationTriggers.
 *
 * Stable identifiers for the events and rules the Automation module reacts
 * to. The event name is the exact name the Shared event dispatcher assigns
 * the Inspection module's `NonConformityRecordedEvent`
 * (`{@see \Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter}`
 * derives it as `<module>.<snake_case_class_name>`), verified against
 * `Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent` and its
 * dispatch site (`AddNonConformityHandler`) — do not rename without updating
 * both.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AutomationTriggers
{
  // #region Constants
  /**
   * The Inspection module's non-conformity recorded event name. Payload
   * fields (see `NonConformityRecordedEvent`): `organizationId`,
   * `inspectionId`, `nonConformityId`, `severity`. The event does **not**
   * carry an equipment or facility identifier.
   */
  public const string NON_CONFORMITY_RECORDED_EVENT = 'inspection.non_conformity_recorded_event';

  /**
   * The rule key for auto-creating a corrective intervention draft when a
   * critical non-conformity is recorded.
   */
  public const string RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC = 'auto_create_intervention_on_critical_nc';
  // #endregion
}

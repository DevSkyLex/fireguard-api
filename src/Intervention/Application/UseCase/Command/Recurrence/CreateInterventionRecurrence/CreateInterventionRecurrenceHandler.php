<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence;

use Intervention\Application\Port\Outbound\{InterventionRecurrencePort, InterventionTemplatePort};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceCreatedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\ValueObject\RecurrenceRule;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function mb_strlen;
use function trim;

/**
 * UseCase CreateInterventionRecurrenceHandler.
 *
 * Creates a recurring intervention schedule against an existing organization
 * template. The initial `next_occurrence_at` is computed eagerly
 * (`rule->nextAfter(now)`) so the recurring materializer has a stable
 * starting point regardless of when the sweep next runs.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionRecurrenceHandler implements CommandHandler
{
  // #region Constants
  /**
   * Inclusive bounds for the lead time, in days.
   */
  public const int MIN_LEAD_TIME_DAYS = 0;

  public const int MAX_LEAD_TIME_DAYS = 90;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePort $recurrences the recurrences port
   * @param InterventionTemplatePort $templates the templates port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private InterventionTemplatePort $templates,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateInterventionRecurrenceCommand $command the command value
   *
   * @return CreateInterventionRecurrenceResult the command result
   */
  public function __invoke(CreateInterventionRecurrenceCommand $command): CreateInterventionRecurrenceResult
  {
    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, 'organization.interventions.plan');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::forOrganizationScope($command->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $template = $this->templates->find($command->templateId);
    if (null === $template || $template->organizationId !== $command->organizationId) {
      throw InterventionNotFoundException::withId($command->templateId);
    }

    $name = self::validatedName($command->name);
    $leadTimeDays = self::validatedLeadTimeDays($command->leadTimeDays);
    $timezone = RecurrenceRule::assertTimezone($command->timezone)->getName();
    $rule = RecurrenceRule::fromValues($command->frequency, $command->interval, $command->anchorDate);

    $now = $this->clock->now();
    $nextOccurrenceAt = $rule->nextAfter($now, $timezone);

    $view = $this->recurrences->create(
      organizationId: $command->organizationId,
      templateId: $command->templateId,
      name: $name,
      siteId: $command->siteId,
      responsibleId: $command->responsibleId,
      frequency: $rule->frequency->value,
      interval: $rule->interval,
      anchorDate: $rule->anchorDate,
      timezone: $timezone,
      leadTimeDays: $leadTimeDays,
      nextOccurrenceAt: $nextOccurrenceAt,
      endAt: $command->endAt,
    );

    $this->eventDispatcher->dispatch(new InterventionRecurrenceCreatedEvent(
      organizationId: $command->organizationId,
      recurrenceId: $view->id,
      templateId: $command->templateId,
      actorUserId: $command->userId,
    ));

    return new CreateInterventionRecurrenceResult($view);
  }

  /**
   * Method validatedName.
   *
   * @since 1.0.0
   *
   * @param string $name the raw name value
   *
   * @return string the trimmed, validated name
   */
  public static function validatedName(string $name): string
  {
    $trimmed = trim($name);
    $length = mb_strlen($trimmed);
    if ($length < 1 || $length > 160) {
      throw new InterventionValidationException('The recurrence name must be between 1 and 160 characters.');
    }

    return $trimmed;
  }

  /**
   * Method validatedLeadTimeDays.
   *
   * @since 1.0.0
   *
   * @param int $leadTimeDays the raw lead time value
   *
   * @return int the validated lead time
   */
  public static function validatedLeadTimeDays(int $leadTimeDays): int
  {
    if ($leadTimeDays < self::MIN_LEAD_TIME_DAYS || $leadTimeDays > self::MAX_LEAD_TIME_DAYS) {
      throw new InterventionValidationException('The recurrence lead time must be between 0 and 90 days.');
    }

    return $leadTimeDays;
  }
  // #endregion
}

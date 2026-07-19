<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateInterventionRecurrenceCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionRecurrenceCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param string $templateId the template id value
   * @param string $name the intervention name given to each materialized occurrence
   * @param ?string $siteId the site override value
   * @param ?string $responsibleId the responsible member override value
   * @param string $frequency the recurrence frequency value (weekly|monthly|quarterly|semiannual|annual)
   * @param int $interval the interval count value, between 1 and 12
   * @param DateTimeImmutable $anchorDate the anchor date value
   * @param string $timezone the IANA timezone value
   * @param int $leadTimeDays the lead time in days value, between 0 and 90
   * @param ?DateTimeImmutable $endAt the optional end date value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $templateId,
    public string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public string $frequency,
    public int $interval,
    public DateTimeImmutable $anchorDate,
    public string $timezone,
    public int $leadTimeDays = 7,
    public ?DateTimeImmutable $endAt = null,
  ) {
  }
}

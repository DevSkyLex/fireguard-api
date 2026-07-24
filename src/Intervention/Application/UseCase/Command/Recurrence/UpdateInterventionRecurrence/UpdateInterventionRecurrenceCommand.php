<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateInterventionRecurrenceCommand.
 *
 * A field is only applied when its `$has*` flag is true (merge-patch
 * semantics), mirroring `UpdateInterventionTemplateCommand`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionRecurrenceCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $recurrenceId the recurrence id value
   * @param ?string $name the name value
   * @param ?string $siteId the site override value
   * @param ?string $responsibleId the responsible member override value
   * @param ?string $frequency the recurrence frequency value
   * @param ?int $interval the interval count value
   * @param ?DateTimeImmutable $anchorDate the anchor date value
   * @param ?string $timezone the IANA timezone value
   * @param ?int $leadTimeDays the lead time in days value
   * @param ?DateTimeImmutable $endAt the end date value
   * @param ?bool $isActive the active flag value
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasSiteId whether the site field was present in the merge-patch request
   * @param bool $hasResponsibleId whether the responsible field was present in the merge-patch request
   * @param bool $hasFrequency whether the frequency field was present in the merge-patch request
   * @param bool $hasInterval whether the interval field was present in the merge-patch request
   * @param bool $hasAnchorDate whether the anchor date field was present in the merge-patch request
   * @param bool $hasTimezone whether the timezone field was present in the merge-patch request
   * @param bool $hasLeadTimeDays whether the lead time field was present in the merge-patch request
   * @param bool $hasEndAt whether the end date field was present in the merge-patch request
   * @param bool $hasIsActive whether the isActive field was present in the merge-patch request
   */
  public function __construct(
    public string $userId,
    public string $recurrenceId,
    public ?string $name = null,
    public ?string $siteId = null,
    public ?string $responsibleId = null,
    public ?string $frequency = null,
    public ?int $interval = null,
    public ?DateTimeImmutable $anchorDate = null,
    public ?string $timezone = null,
    public ?int $leadTimeDays = null,
    public ?DateTimeImmutable $endAt = null,
    public ?bool $isActive = null,
    public bool $hasName = false,
    public bool $hasSiteId = false,
    public bool $hasResponsibleId = false,
    public bool $hasFrequency = false,
    public bool $hasInterval = false,
    public bool $hasAnchorDate = false,
    public bool $hasTimezone = false,
    public bool $hasLeadTimeDays = false,
    public bool $hasEndAt = false,
    public bool $hasIsActive = false,
  ) {
  }
}

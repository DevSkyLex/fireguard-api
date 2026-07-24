<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\UpdateInterventionTemplate;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateInterventionTemplateCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionTemplateCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $templateId the template id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $description the description value, only applied when `$hasDescription` is true
   * @param ?string $type the intervention type value, only applied when `$hasType` is true
   * @param ?string $priority the intervention priority value, only applied when `$hasPriority` is true
   * @param ?string $defaultSiteId the default site id value, only applied when `$hasDefaultSiteId` is true
   * @param ?string $defaultResponsibleId the default responsible member id value, only applied when `$hasDefaultResponsibleId` is true
   * @param ?string $duration the ISO-8601 duration string value, only applied when `$hasDuration` is true
   * @param ?list<string> $labelIds the organization label ids value, only applied when `$hasLabelIds` is true
   * @param ?list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, only applied when `$hasItems` is true
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasDescription whether the description field was present in the merge-patch request
   * @param bool $hasType whether the type field was present in the merge-patch request
   * @param bool $hasPriority whether the priority field was present in the merge-patch request
   * @param bool $hasDefaultSiteId whether the defaultSiteId field was present in the merge-patch request
   * @param bool $hasDefaultResponsibleId whether the defaultResponsibleId field was present in the merge-patch request
   * @param bool $hasDuration whether the duration field was present in the merge-patch request
   * @param bool $hasLabelIds whether the labelIds field was present in the merge-patch request
   * @param bool $hasItems whether the items field was present in the merge-patch request
   */
  public function __construct(
    public string $userId,
    public string $templateId,
    public ?string $name,
    public ?string $description,
    public ?string $type,
    public ?string $priority,
    public ?string $defaultSiteId,
    public ?string $defaultResponsibleId,
    public ?string $duration,
    public ?array $labelIds,
    public ?array $items,
    public bool $hasName = false,
    public bool $hasDescription = false,
    public bool $hasType = false,
    public bool $hasPriority = false,
    public bool $hasDefaultSiteId = false,
    public bool $hasDefaultResponsibleId = false,
    public bool $hasDuration = false,
    public bool $hasLabelIds = false,
    public bool $hasItems = false,
  ) {
  }
}

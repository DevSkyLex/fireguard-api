<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateInterventionTemplateCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionTemplateCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param ?string $description the description value
   * @param string $type the intervention type value (site_setup|inventory|inspection_campaign)
   * @param string $priority the intervention priority value (low|normal|high|urgent)
   * @param ?string $defaultSiteId the default site id value
   * @param ?string $defaultResponsibleId the default responsible member id value
   * @param ?string $duration the ISO-8601 duration string value (e.g. `P14D`)
   * @param list<string> $labelIds the organization label ids value
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, in position order
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $name,
    public ?string $description,
    public string $type,
    public string $priority,
    public ?string $defaultSiteId,
    public ?string $defaultResponsibleId,
    public ?string $duration,
    public array $labelIds = [],
    public array $items = [],
  ) {
  }
}

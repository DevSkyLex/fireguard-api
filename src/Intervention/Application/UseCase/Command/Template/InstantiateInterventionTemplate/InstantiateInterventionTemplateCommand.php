<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase InstantiateInterventionTemplateCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InstantiateInterventionTemplateCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $templateId the template id value
   * @param ?string $name the intervention name override, or null to use the template name
   * @param ?string $siteId the site id override, or null to use the template default site
   * @param ?string $responsibleId the responsible member id override, or null to use the template default responsible
   * @param ?DateTimeImmutable $plannedStartAt the planned start date, used with the template duration to derive `dueAt`
   */
  public function __construct(
    public string $userId,
    public string $templateId,
    public ?string $name = null,
    public ?string $siteId = null,
    public ?string $responsibleId = null,
    public ?DateTimeImmutable $plannedStartAt = null,
  ) {
  }
}

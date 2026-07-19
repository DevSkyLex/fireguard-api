<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase GenerateInspectionCampaignCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateInspectionCampaignCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $actorUserId the acting user id value
   * @param string $name the intervention name value
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param DateTimeImmutable $dueBefore the upper bound on the next due date
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $name,
    public ?string $facilityId,
    public ?string $equipmentType,
    public DateTimeImmutable $dueBefore,
  ) {
  }
}

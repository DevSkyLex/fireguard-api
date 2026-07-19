<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GenerateInspectionCampaignResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateInspectionCampaignResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the created intervention identifier
   * @param int $number the per-organization sequential intervention number
   * @param int $workItemsCount the number of seeded work items
   */
  public function __construct(
    public string $interventionId,
    public int $number,
    public int $workItemsCount,
  ) {
  }
}

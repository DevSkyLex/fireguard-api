<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\InstantiateInterventionTemplate;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase InstantiateInterventionTemplateResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InstantiateInterventionTemplateResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the created intervention id value
   * @param int $number the per-organization sequential intervention number
   */
  public function __construct(
    public string $interventionId,
    public int $number,
  ) {
  }
}

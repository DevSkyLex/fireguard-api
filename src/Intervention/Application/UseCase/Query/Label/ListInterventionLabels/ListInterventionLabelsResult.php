<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Label\ListInterventionLabels;

use Intervention\Application\Contract\Label\InterventionLabelPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionLabelsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionLabelsResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelPage $page the page value
   */
  public function __construct(public InterventionLabelPage $page)
  {
  }
}

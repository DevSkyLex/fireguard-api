<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Template\GetInterventionTemplate;

use Intervention\Application\Contract\Template\InterventionTemplateView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInterventionTemplateResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionTemplateResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateView $template the template value
   */
  public function __construct(public InterventionTemplateView $template)
  {
  }
}

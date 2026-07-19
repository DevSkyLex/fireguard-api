<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate;

use Intervention\Application\Contract\Template\InterventionTemplateView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInterventionTemplateResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionTemplateResult implements ResultMessage
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

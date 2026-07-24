<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\CreateInterventionLabel;

use Intervention\Application\Contract\Label\InterventionLabelView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateInterventionLabelResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionLabelResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionLabelView $label the label value
   */
  public function __construct(public InterventionLabelView $label)
  {
  }
}

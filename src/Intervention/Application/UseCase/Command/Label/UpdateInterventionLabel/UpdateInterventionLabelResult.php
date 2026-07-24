<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel;

use Intervention\Application\Contract\Label\InterventionLabelView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateInterventionLabelResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionLabelResult implements ResultMessage
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

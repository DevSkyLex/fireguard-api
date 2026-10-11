<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\RenderEquipmentLabels;

use Shared\Application\Message\ResultMessage;

/** Carries QR label PDF bytes. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class RenderEquipmentLabelsResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $contents)
  {
  }
}

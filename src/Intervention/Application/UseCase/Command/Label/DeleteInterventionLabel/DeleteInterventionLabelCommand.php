<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\DeleteInterventionLabel;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInterventionLabelCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionLabelCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $labelId the label id value
   */
  public function __construct(
    public string $userId,
    public string $labelId,
  ) {
  }
}

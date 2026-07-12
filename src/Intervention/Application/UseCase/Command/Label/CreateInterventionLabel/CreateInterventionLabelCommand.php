<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\CreateInterventionLabel;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateInterventionLabelCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionLabelCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param string $color the color value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $name,
    public string $color,
  ) {
  }
}

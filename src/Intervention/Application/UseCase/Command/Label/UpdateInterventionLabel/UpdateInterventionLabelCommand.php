<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Label\UpdateInterventionLabel;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateInterventionLabelCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionLabelCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $labelId the label id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $color the color value, only applied when `$hasColor` is true
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasColor whether the color field was present in the merge-patch request
   */
  public function __construct(
    public string $userId,
    public string $labelId,
    public ?string $name,
    public ?string $color,
    public bool $hasName,
    public bool $hasColor,
  ) {
  }
}

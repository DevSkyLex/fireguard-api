<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\DeleteInterventionTemplate;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInterventionTemplateCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionTemplateCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $templateId the template id value
   */
  public function __construct(
    public string $userId,
    public string $templateId,
  ) {
  }
}

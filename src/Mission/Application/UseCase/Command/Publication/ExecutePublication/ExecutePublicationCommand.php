<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Publication\ExecutePublication;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ExecutePublicationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExecutePublicationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the ExecutePublicationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $publicationId the publication id value
   */
  public function __construct(public string $publicationId)
  {
  }
}

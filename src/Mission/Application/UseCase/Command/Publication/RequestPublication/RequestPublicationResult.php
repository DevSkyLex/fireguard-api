<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Command\Publication\RequestPublication;

use Mission\Application\Contract\Publication\PublicationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RequestPublicationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPublicationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestPublicationResult class.
   *
   * @since 1.0.0
   *
   * @param PublicationView $publication the publication value
   */
  public function __construct(public PublicationView $publication)
  {
  }
}

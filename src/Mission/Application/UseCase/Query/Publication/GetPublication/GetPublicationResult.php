<?php

declare(strict_types=1);

namespace Mission\Application\UseCase\Query\Publication\GetPublication;

use Mission\Application\Contract\Publication\PublicationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetPublicationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPublicationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPublicationResult class.
   *
   * @since 1.0.0
   *
   * @param PublicationView $publication the publication value
   */
  public function __construct(public PublicationView $publication)
  {
  }
}

<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\StartFederation;

use Shared\Application\Message\ResultMessage;

/**
 * StartFederationResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartFederationResult implements ResultMessage
{
  public function __construct(public string $authorizationUrl)
  {
  }
}

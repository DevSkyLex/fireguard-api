<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Federation\ListFederatedProviders;

use Auth\Application\Contract\Federation\FederatedProvider;
use Shared\Application\Message\ResultMessage;

/**
 * ListFederatedProvidersResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFederatedProvidersResult implements ResultMessage
{
  /**
   * @param list<FederatedProvider> $providers enabled provider identities
   */
  public function __construct(public array $providers)
  {
  }
}

<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Presence\GetPresencePreference;

use Shared\Application\Message\QueryHandler;
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;

/**
 * UseCase GetPresencePreferenceHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private PresencePreferenceRepositoryPort $preferences)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(GetPresencePreferenceQuery $query): GetPresencePreferenceResult
  {
    $preference = $this->preferences->get($query->userId);

    return new GetPresencePreferenceResult($preference->doNotDisturb, $preference->revision, $preference->invisible);
  }
}

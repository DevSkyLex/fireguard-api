<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Presence\UpdatePresencePreference;

use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;
use User\Application\Contract\Presence\PresencePreferenceChangedEvent;
use User\Application\Port\Outbound\PresencePreferenceRepositoryPort;

/**
 * UseCase UpdatePresencePreferenceHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePresencePreferenceHandler implements CommandHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private PresencePreferenceRepositoryPort $preferences, private EventDispatcherPort $events, private LoggerPort $logger)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(UpdatePresencePreferenceCommand $command): UpdatePresencePreferenceResult
  {
    // Enabling a mode clears the other in the same atomic write, including across devices.
    // Invisible wins an ambiguous legacy request containing both true flags.
    $doNotDisturb = true === $command->invisible ? false : $command->doNotDisturb;
    $invisible = true === $doNotDisturb ? false : $command->invisible;
    $write = $this->preferences->save($command->userId, $doNotDisturb, $invisible);
    $preference = $write->preference;
    if ($write->changed) {
      try {
        $this->events->dispatch(new PresencePreferenceChangedEvent($command->userId, $preference->doNotDisturb, $preference->revision, $preference->invisible));
      } catch (Throwable) {
        $this->logger->warning('Presence preference event delivery failed after commit.', ['userId' => $command->userId]);
      }
    }

    return new UpdatePresencePreferenceResult($preference->doNotDisturb, $preference->revision, $preference->invisible);
  }
}

<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventDispatcher;

use Psr\Log\LoggerInterface;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function end;
use function explode;
use function preg_replace;
use function strtolower;

/**
 * Adapter SymfonyEventDispatcherAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SymfonyEventDispatcherAdapter implements EventDispatcherPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * SymfonyEventDispatcherAdapter class.
   *
   * @since 1.0.0
   *
   * @param EventDispatcherInterface $eventDispatcher the Symfony event dispatcher
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  public function dispatch(object $event): void
  {
    $eventName = $this->getEventName($event);

    $this->logger->debug('Dispatching domain event', [
      'event' => $eventName,
    ]);

    $this->eventDispatcher->dispatch($event, $eventName);
  }

  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }

  /**
   * Method getEventName.
   *
   * Gets the event name from the event class.
   *
   * @since 1.0.0
   *
   * @param object $event the event
   *
   * @return string the event name
   */
  private function getEventName(object $event): string
  {
    $className = $event::class;
    $parts = explode('\\', $className);
    $module = strtolower($parts[0]);
    $shortName = end($parts);

    // Convert CamelCase to snake_case
    $snakeCase = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));

    return $module . '.' . $snakeCase;
  }
  // #endregion
}

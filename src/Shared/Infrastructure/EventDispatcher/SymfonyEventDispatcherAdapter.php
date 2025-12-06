<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventDispatcher;

use Shared\Application\Port\Outbound\EventDispatcherPort;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter SymfonyEventDispatcherAdapter
 * @final
 *
 * Adapts Symfony EventDispatcher to the domain port.
 *
 * @category Adapter
 * @package Shared\Infrastructure\EventDispatcher
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SymfonyEventDispatcherAdapter implements EventDispatcherPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * SymfonyEventDispatcherAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EventDispatcherInterface $eventDispatcher The Symfony event dispatcher.
   * @param LoggerInterface $logger The logger.
   */
  public function __construct(
    private EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function dispatch(object $event): void
  {
    $eventName = $this->getEventName($event);

    $this->logger->debug('Dispatching domain event', [
      'event' => $eventName,
    ]);

    $this->eventDispatcher->dispatch($event, $eventName);
  }

  /**
   * {@inheritDoc}
   */
  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }

  /**
   * Method getEventName
   *
   * Gets the event name from the event class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param object $event The event.
   *
   * @return string The event name.
   */
  private function getEventName(object $event): string
  {
    $className = $event::class;
    $parts = explode('\\', $className);
    $shortName = end($parts);

    // Convert CamelCase to snake_case
    $snakeCase = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));

    return 'auth.' . $snakeCase;
  }
  //#endregion
}

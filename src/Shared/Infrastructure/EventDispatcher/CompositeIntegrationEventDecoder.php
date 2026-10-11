<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventDispatcher;

use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventDecoderPort};

/**
 * Class CompositeIntegrationEventDecoder
 *
 * Delegates legacy conversion to the owning modules before subscribers receive the event.
 *
 * @category Adapter
 */
final readonly class CompositeIntegrationEventDecoder implements IntegrationEventDecoderPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives owner converters registered through the integration event service tag.
   *
   * @access public
   *
   * @param iterable<IntegrationEventConverterPort> $converters owner-local converters
   *
   * @return void
   */
  public function __construct(private iterable $converters)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method decode
   *
   * Leaves public event instances intact so their stable names, payloads and identity remain unchanged.
   *
   * @access public
   *
   * @param object $event deserialized event payload
   *
   * @return object public snapshot or unchanged event
   */
  public function decode(object $event): object
  {
    foreach ($this->converters as $converter) {
      $converted = $converter->convert($event);
      if (null !== $converted) {
        return $converted;
      }
    }

    return $event;
  }
  // #endregion
}

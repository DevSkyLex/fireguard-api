<?php

declare(strict_types=1);

namespace Webhook\Application\Service;

use InvalidArgumentException;
use Webhook\Application\Contract\Event\WebhookEventDescriptor;
use Webhook\Application\Port\Outbound\{WebhookEventCatalogPort, WebhookEventSourcePort};

use function array_values;
use function sprintf;

/**
 * Class WebhookEventCatalogRegistry
 *
 * Indexes owner contributions and rejects duplicate public keys or ambiguous source mappings.
 *
 * @category Service
 */
final readonly class WebhookEventCatalogRegistry implements WebhookEventCatalogPort
{
  // #region Properties
  /**
   * Property descriptors
   *
   * @var array<string, WebhookEventDescriptor>
   */
  private array $descriptors;
  // #endregion

  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param iterable<WebhookEventSourcePort> $sources the tagged owner sources
   */
  public function __construct(iterable $sources)
  {
    $descriptors = [];
    $eventNames = [];
    foreach ($sources as $source) {
      foreach ($source->descriptors() as $descriptor) {
        if (isset($descriptors[$descriptor->value]) || isset($eventNames[$descriptor->sourceEventName])) {
          throw new InvalidArgumentException(sprintf('Duplicate webhook event contribution "%s".', $descriptor->value));
        }
        $descriptors[$descriptor->value] = $descriptor;
        $eventNames[$descriptor->sourceEventName] = true;
      }
    }
    $this->descriptors = $descriptors;
  }
  // #endregion

  // #region Methods
  /**
   * Method descriptors
   *
   * @access public
   *
   * @return list<WebhookEventDescriptor> the installed metadata in contribution order
   */
  public function descriptors(): array
  {
    return array_values($this->descriptors);
  }

  /**
   * Method isAllowedEventType
   *
   * @access public
   *
   * @param string $value the proposed public type
   *
   * @return bool whether a contributor provides the public type
   */
  public function isAllowedEventType(string $value): bool
  {
    return isset($this->descriptors[$value]);
  }
  // #endregion
}

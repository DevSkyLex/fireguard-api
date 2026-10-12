<?php

declare(strict_types=1);

namespace Calendar\Application\Service;

use Calendar\Application\Port\Outbound\Feed\CalendarFeedSourcePort;
use InvalidArgumentException;

use function sprintf;

/**
 * Class CalendarFeedSourceRegistry
 *
 * Materializes tagged contributors once and rejects ambiguous source identities at construction.
 * Configured iteration order is preserved for the feed's source completeness states.
 *
 * @category Service
 */
final readonly class CalendarFeedSourceRegistry
{
  // #region Properties
  /**
   * Property sources
   *
   * @var array<string, CalendarFeedSourcePort> contributors indexed by stable source key
   */
  private array $sources;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Collects each configured source without reading its business data.
   *
   * @access public
   *
   * @param iterable<CalendarFeedSourcePort> $sources tagged calendar feed contributors
   *
   * @return void
   *
   * @throws InvalidArgumentException when two contributors publish the same source key
   */
  public function __construct(iterable $sources)
  {
    $catalog = [];
    foreach ($sources as $source) {
      $key = $source->sourceKey();
      if (isset($catalog[$key])) {
        throw new InvalidArgumentException(sprintf('Duplicate calendar feed source key "%s".', $key));
      }
      $catalog[$key] = $source;
    }
    $this->sources = $catalog;
  }
  // #endregion

  // #region Methods
  /**
   * Method all
   *
   * Returns contributors in the configured order, including an empty catalog when none are registered.
   *
   * @access public
   *
   * @return array<string, CalendarFeedSourcePort> the source catalog
   */
  public function all(): array
  {
    return $this->sources;
  }
  // #endregion
}

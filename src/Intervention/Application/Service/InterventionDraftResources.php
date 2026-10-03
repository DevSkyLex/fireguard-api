<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Port\Inbound\InterventionDraftResourcesPort;
use Intervention\Application\Port\Outbound\InterventionDraftPublisherPort;

use function array_unique;
use function array_values;

/**
 * Service InterventionDraftResources.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftResources implements InterventionDraftResourcesPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param iterable<InterventionDraftPublisherPort> $publishers the lazy resource-owner inventory
   */
  public function __construct(private iterable $publishers)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function draftResourceIris(string $interventionId): array
  {
    $iris = [];
    foreach ($this->publishers as $publisher) {
      $iris = [...$iris, ...$publisher->draftResourceIris($interventionId)];
    }

    return array_values(array_unique($iris));
  }
  // #endregion
}

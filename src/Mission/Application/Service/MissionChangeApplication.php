<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Mission\Application\Port\Outbound\MissionChangeApplierPort;
use Mission\Domain\Exception\MissionConflictException;

use function sprintf;

/**
 * Service MissionChangeApplication.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionChangeApplication
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<MissionChangeApplierPort> $appliers
   */
  public function __construct(private iterable $appliers)
  {
  }

  /**
   * Method apply.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $resource the resource value
   * @param array<string, mixed> $patch
   */
  public function apply(string $organizationId, string $resource, array $patch): void
  {
    foreach ($this->appliers as $applier) {
      if ($applier->supports($resource)) {
        $applier->apply($organizationId, $resource, $patch);

        return;
      }
    }

    throw new MissionConflictException(sprintf('Unsupported mission change resource "%s".', $resource));
  }
}

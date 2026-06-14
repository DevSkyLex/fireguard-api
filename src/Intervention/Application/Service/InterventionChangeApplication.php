<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionChangeApplierPort;
use Intervention\Domain\Exception\InterventionConflictException;

use function sprintf;

/**
 * Service InterventionChangeApplication.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionChangeApplication
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<InterventionChangeApplierPort> $appliers
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

    throw new InterventionConflictException(sprintf('Unsupported intervention change resource "%s".', $resource));
  }
}

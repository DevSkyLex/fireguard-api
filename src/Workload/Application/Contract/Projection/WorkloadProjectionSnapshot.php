<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Projection;

/**
 * Contract WorkloadProjectionSnapshot.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadProjectionSnapshot
{
  /**
   * @since 1.0.0
   *
   * @param WorkloadProjectionView $view projection view or persisted record being translated
   * @param string $fingerprint deterministic signature of the relevant projection inputs
   */
  public function __construct(public WorkloadProjectionView $view, public string $fingerprint)
  {
  }
}

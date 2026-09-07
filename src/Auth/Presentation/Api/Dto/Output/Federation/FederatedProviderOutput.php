<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

/**
 * DTO FederatedProviderOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedProviderOutput
{
  public function __construct(
    public string $provider,
    public bool $enabled = true,
  ) {
  }
}

<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO HealthOutput.
 *
 * Output DTO for health check responses.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HealthOutput
{
  // #region Properties
  #[Groups(['health:read'])]
  public string $status = '';

  #[Groups(['health:read'])]
  public string $timestamp = '';

  #[Groups(['health:read:details'])]
  public ?bool $database = null;

  #[Groups(['health:read:details'])]
  public ?bool $cache = null;

  #[Groups(['health:read'])]
  public string $version = '1.0.0';
  // #endregion
}

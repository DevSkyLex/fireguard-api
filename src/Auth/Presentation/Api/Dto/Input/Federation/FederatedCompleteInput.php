<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\Federation;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO FederatedCompleteInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedCompleteInput
{
  #[Assert\Length(max: 4096)]
  public ?string $code = null;

  #[Assert\Length(max: 128)]
  public ?string $error = null;

  #[Assert\NotBlank]
  #[Assert\Length(max: 256)]
  public ?string $state = null;
}

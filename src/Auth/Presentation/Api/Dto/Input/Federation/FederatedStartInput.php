<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\Federation;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO FederatedStartInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedStartInput
{
  #[Assert\Length(max: 500)]
  #[SerializedName('return_url')]
  public string $returnUrl = '/';
}

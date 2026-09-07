<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO FederatedStartOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedStartOutput
{
  public function __construct(
    #[SerializedName('authorization_url')]
    public string $authorizationUrl,
  ) {
  }
}

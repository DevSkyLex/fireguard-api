<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Auth\Application\Contract\Federation\FederatedConnection;
use Symfony\Component\Serializer\Attribute\SerializedName;

use const DATE_ATOM;

/**
 * DTO FederatedConnectionOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedConnectionOutput
{
  public string $provider;

  public string $email;

  #[SerializedName('connected_at')]
  public string $connectedAt;

  #[SerializedName('last_used_at')]
  public string $lastUsedAt;

  public static function fromContract(FederatedConnection $connection): self
  {
    $output = new self();
    $output->provider = $connection->provider->value;
    $output->email = $connection->email;
    $output->connectedAt = $connection->connectedAt->format(DATE_ATOM);
    $output->lastUsedAt = $connection->lastUsedAt->format(DATE_ATOM);

    return $output;
  }
}

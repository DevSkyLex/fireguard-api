<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Federation;

use Auth\Application\Contract\Federation\FederatedConnections;
use Symfony\Component\Serializer\Attribute\SerializedName;

use function array_map;

/**
 * DTO FederatedConnectionsOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FederatedConnectionsOutput
{
  #[SerializedName('password_configured')]
  public bool $passwordConfigured;

  #[SerializedName('last_sign_in_method')]
  public ?string $lastSignInMethod;

  /**
   * @var list<FederatedConnectionOutput>
   */
  public array $connections;

  public static function fromContract(FederatedConnections $connections): self
  {
    $output = new self();
    $output->passwordConfigured = $connections->passwordConfigured;
    $output->lastSignInMethod = $connections->lastSignInMethod;
    $output->connections = array_map(FederatedConnectionOutput::fromContract(...), $connections->connections);

    return $output;
  }
}

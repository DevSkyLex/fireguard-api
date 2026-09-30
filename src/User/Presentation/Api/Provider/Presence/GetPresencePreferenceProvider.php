<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\UseCase\Query\Presence\GetPresencePreference\{GetPresencePreferenceQuery, GetPresencePreferenceResult};
use User\Presentation\Api\Dto\Output\Presence\PresencePreferenceOutput;

/**
 * @implements ProviderInterface<PresencePreferenceOutput>
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresencePreferenceProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queryBus, private CurrentActorPort $actor)
  {
  }

  /**
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): PresencePreferenceOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    /**
     * @var GetPresencePreferenceResult $result
     */
    $result = $this->queryBus->ask(new GetPresencePreferenceQuery($userId));
    $output = new PresencePreferenceOutput();
    $output->doNotDisturb = $result->doNotDisturb;
    $output->revision = $result->revision;
    $output->invisible = $result->invisible;

    return $output;
  }
}

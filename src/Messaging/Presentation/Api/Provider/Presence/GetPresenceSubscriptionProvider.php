<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription\{GetPresenceSubscriptionQuery, GetPresenceSubscriptionResult};
use Messaging\Presentation\Api\Dto\Output\PresenceSubscriptionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Symfony\Component\Uid\Uuid;

use function is_string;
use function preg_match;

/**
 * @implements ProviderInterface<PresenceSubscriptionOutput>
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPresenceSubscriptionProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queryBus, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): PresenceSubscriptionOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organization = $this->requests->getCurrentRequest()?->query->get('organization');
    foreach ($operation->getParameters() ?? [] as $name => $parameter) {
      if ('organization' === $name && is_string($parameter->getValue())) {
        $organization = $parameter->getValue();
      }
    }
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    if (1 !== preg_match('#(?:^|/api/organizations/)([0-9a-fA-F-]{36})$#', $organization, $matches) || !Uuid::isValid($matches[1])) {
      throw new BadRequestHttpException('Invalid organization IRI.');
    }
    /**
     * @var GetPresenceSubscriptionResult $result
     */
    $result = $this->queryBus->ask(new GetPresenceSubscriptionQuery($userId, $matches[1]));
    $output = new PresenceSubscriptionOutput();
    $output->topic = $result->topic;
    $output->token = $result->token;
    $output->expiresAt = $result->expiresAt;

    return $output;
  }
}

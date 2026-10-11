<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Subscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription\{GetMessagingSubscriptionQuery, GetMessagingSubscriptionResult};
use Messaging\Presentation\Api\Dto\Output\MessagingSubscriptionOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Provider GetMessagingSubscriptionProvider.
 *
 * Dispatches the authorized subscription query and translates its opaque token
 * and topic. Application owns conversation access and Infrastructure owns JWT
 * claims, expiration and the exact private topic vocabulary.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MessagingSubscriptionOutput>
 */
final readonly class GetMessagingSubscriptionProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return MessagingSubscriptionOutput the subscription output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessagingSubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $conversationId = $uriVariables['id'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var GetMessagingSubscriptionResult $result */
      $result = $this->queryBus->ask(new GetMessagingSubscriptionQuery($user->getId(), $conversationId));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    $output = new MessagingSubscriptionOutput();
    $output->token = $result->subscription->token;
    $output->topic = $result->subscription->topic;

    return $output;
  }
}

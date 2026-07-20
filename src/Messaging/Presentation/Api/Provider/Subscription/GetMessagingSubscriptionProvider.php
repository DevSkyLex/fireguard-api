<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Subscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Messaging\Infrastructure\Adapter\Realtime\MercureMessagingRealtimePublisherAdapter;
use Messaging\Presentation\Api\Dto\Output\MessagingSubscriptionOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Provider GetMessagingSubscriptionProvider.
 *
 * Generates a Mercure subscriber JWT restricted to ONE conversation's
 * private topic (clone of Notification's `GetMercureSubscriptionProvider`).
 * Reuses `GetConversationHandler` (through the query bus) to enforce the
 * exact same access rule a `GET /api/conversations/{id}` call would (subject
 * read permission + `organization.messaging.read`) instead of duplicating
 * it here — this provider stays a thin topic/token builder, matching the
 * Notification precedent.
 *
 * @category Provider
 *
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
   * @param TokenFactoryInterface $defaultFactory the Mercure JWT token factory
   * @param int $tokenTtl the subscriber token lifetime in seconds
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private TokenFactoryInterface $defaultFactory,
    #[Autowire(value: '%env(default:mercure_subscriber_token_ttl:int:MERCURE_SUBSCRIBER_TOKEN_TTL)%')]
    private int $tokenTtl = 900,
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
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $conversationId = $uriVariables['id'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var GetConversationResult $result */
      $result = $this->queryBus->ask(new GetConversationQuery($user->getId(), $conversationId));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    $topic = MercureMessagingRealtimePublisherAdapter::topic($result->conversation->organizationId, $conversationId);
    $token = $this->defaultFactory->create(
      subscribe: [$topic],
      publish: [],
      additionalClaims: ['exp' => new DateTimeImmutable(sprintf('+%d seconds', $this->tokenTtl))],
    );

    $output = new MessagingSubscriptionOutput();
    $output->token = $token;
    $output->topic = $topic;

    return $output;
  }
}

<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadQuery, GetAssistantThreadResult};
use Assistant\Infrastructure\Adapter\Realtime\MercureAssistantRealtimePublisherAdapter;
use Assistant\Presentation\Api\Dto\Output\AssistantThreadSubscriptionOutput;
use Assistant\Presentation\Api\Trait\AssistantExceptionMapperTrait;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Throwable;

use function is_string;

/**
 * Provider GetAssistantThreadSubscriptionProvider.
 *
 * Generates a Mercure subscriber JWT restricted to ONE assistant thread's
 * private topic (clone of Messaging's `GetMessagingSubscriptionProvider`,
 * itself a clone of Notification's `GetMercureSubscriptionProvider`). Reuses
 * `GetAssistantThreadHandler` (through the query bus) to enforce the exact
 * same access rule a `GET .../assistant/threads/{id}` call would
 * (`organization.assistant.use` + the thread must belong to the requesting
 * member) instead of duplicating it here — this provider stays a thin
 * topic/token builder, matching the Messaging/Notification precedent. The
 * JWT is only ever minted AFTER that same authorization succeeds.
 *
 * **Never reuses** the Messaging conversation topic scheme or the
 * Notification topic scheme — see
 * `Assistant\Application\Port\Outbound\AssistantRealtimePublisherPort`'s
 * docblock for why.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AssistantThreadSubscriptionOutput>
 */
final readonly class GetAssistantThreadSubscriptionProvider implements ProviderInterface
{
  use AssistantExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security service
   * @param TokenFactoryInterface $defaultFactory the Mercure JWT token factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private TokenFactoryInterface $defaultFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return AssistantThreadSubscriptionOutput the subscription output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): AssistantThreadSubscriptionOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $threadId = $uriVariables['threadId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($threadId) || '' === $threadId) {
      throw new BadRequestHttpException('OrganizationId and threadId URI parameters are required.');
    }

    try {
      /** @var GetAssistantThreadResult $result */
      $result = $this->queryBus->ask(new GetAssistantThreadQuery(
        organizationId: $organizationId,
        threadId: $threadId,
        actorUserId: $user->getId(),
        messagesPage: 1,
        messagesItemsPerPage: 1,
      ));
    } catch (Throwable $exception) {
      throw $this->mapAssistantException($exception);
    }

    $topic = MercureAssistantRealtimePublisherAdapter::topic($result->thread->organizationId, $result->thread->id);
    $token = $this->defaultFactory->create(subscribe: [$topic], publish: []);

    $output = new AssistantThreadSubscriptionOutput();
    $output->token = $token;
    $output->topic = $topic;

    return $output;
  }
  // #endregion
}

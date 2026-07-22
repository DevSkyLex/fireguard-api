<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Message\GetConversationActivity\{GetConversationActivityQuery, GetConversationActivityResult};
use Messaging\Presentation\Api\Dto\Output\ConversationActivityBucketOutput;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider GetConversationActivityProvider.
 *
 * Serves `GET /conversations/{conversationId}/activity` — the conversation
 * activity heatmap. Not paginated: the response is always a small, bounded,
 * fixed-size list of daily buckets (defaults to 26, capped at 366 by the
 * handler), so it is returned as a plain array rather than through
 * `TraversablePaginator`.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ConversationActivityBucketOutput>
 */
final readonly class GetConversationActivityProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  // #region Constants
  private const int DEFAULT_BUCKETS = 26;

  private const int MIN_BUCKETS = 1;

  private const int MAX_BUCKETS = 366;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return list<ConversationActivityBucketOutput> the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->user();
    $conversationId = $uriVariables['conversationId'] ?? null;
    if (!is_string($conversationId) || '' === $conversationId) {
      throw new BadRequestHttpException('The conversationId URI parameter is required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $buckets = max(self::MIN_BUCKETS, min(self::MAX_BUCKETS, $query?->getInt('buckets', self::DEFAULT_BUCKETS) ?? self::DEFAULT_BUCKETS));

    try {
      /** @var GetConversationActivityResult $result */
      $result = $this->queryBus->ask(new GetConversationActivityQuery(
        userId: $user->getId(),
        conversationId: $conversationId,
        buckets: $buckets,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return array_map(self::toOutput(...), $result->buckets);
  }

  /**
   * Method toOutput.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array{bucket: string, count: int} $bucket the bucket value
   *
   * @return ConversationActivityBucketOutput the mapped bucket
   */
  private static function toOutput(array $bucket): ConversationActivityBucketOutput
  {
    $output = new ConversationActivityBucketOutput();
    $output->bucket = $bucket['bucket'];
    $output->count = $bucket['count'];

    return $output;
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}

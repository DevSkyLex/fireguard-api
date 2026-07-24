<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\GetConversationActivity;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\QueryHandler;

use function max;
use function min;

/**
 * UseCase GetConversationActivityHandler.
 *
 * Gates the activity heatmap by the exact same access rule as a normal
 * message read — mirrors `ListMessagesHandler`/`ListConversationAttachmentsHandler`/
 * `ListPinnedMessagesHandler` byte for byte — via `MessagingAccessPolicy`, so
 * there is a single access path for reading a conversation's content.
 * Zero-fills every requested daily bucket (including empty ones, in UTC), so
 * a chart never has to infer gaps — the same "always emit every bucket"
 * contract `DashboardSeriesBuilder::normalizeSeries()` uses for the
 * Organization dashboard, kept local to this handler rather than reused
 * cross-module (this heatmap has no granularity/comparison/timezone filters
 * to share, and Application code must not depend on another module's
 * Application layer).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationActivityHandler implements QueryHandler
{
  // #region Constants
  /**
   * Smallest number of daily buckets a caller may request.
   */
  private const int MIN_BUCKETS = 1;

  /**
   * Largest number of daily buckets a caller may request — mirrors
   * `DashboardSeriesBuilder::MAX_TREND_PERIOD_DAYS`, a generous ceiling
   * rather than a meaningful product limit.
   */
  private const int MAX_BUCKETS = 366;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingMessageRepositoryPort $messages,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetConversationActivityQuery $query the query value
   *
   * @return GetConversationActivityResult the use case result
   */
  public function __invoke(GetConversationActivityQuery $query): GetConversationActivityResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);
      $this->accessPolicy->assertCanReadChannel($query->userId, $conversation->organizationId, $conversation->id, $memberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.read';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $conversation->organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanReadThread($query->userId, $conversation->organizationId, $requiredSubjectPermission);
    }

    $bucketCount = max(self::MIN_BUCKETS, min(self::MAX_BUCKETS, $query->buckets));

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $periodEnd = $now->setTime(23, 59, 59);
    $periodStart = $now->sub(new DateInterval('P' . ($bucketCount - 1) . 'D'))->setTime(0, 0);

    $counts = $this->messages->countByConversationDay($query->conversationId, $periodStart, $periodEnd);

    $buckets = [];
    for ($cursor = $periodStart; $cursor <= $periodEnd; $cursor = $cursor->add(new DateInterval('P1D'))) {
      $bucketKey = $cursor->format('Y-m-d');
      $buckets[] = ['bucket' => $bucketKey, 'count' => $counts[$bucketKey] ?? 0];
    }

    return new GetConversationActivityResult($buckets);
  }
  // #endregion
}

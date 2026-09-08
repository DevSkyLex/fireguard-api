<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use DateTimeImmutable;
use DateTimeZone;
use Shared\Application\Message\QueryHandler;

use function sprintf;

/**
 * UseCase ResolveCalendarFeedTokenHandler.
 *
 * Hash-based lookup for the public `.ics` endpoint: an unknown and a
 * revoked token are indistinguishable (both raise
 * {@see CalendarFeedTokenNotFoundException} — no oracle). Records
 * `lastUsedAt` at most once per hour (see
 * {@see \Calendar\Domain\Model\FeedToken\CalendarFeedToken::shouldRecordUsage()})
 * so polling calendar clients do not turn every fetch into a write. Computes
 * the fixed feed window (now minus 30 days / now plus 180 days, well under
 * the feed's 366-day cap) so the caller can reuse the interactive feed use
 * case as-is.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCalendarFeedTokenHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant WINDOW_PAST_DAYS.
   *
   * How far back the subscribed feed window reaches.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int WINDOW_PAST_DAYS = 30;

  /**
   * Constant WINDOW_FUTURE_DAYS.
   *
   * How far ahead the subscribed feed window reaches.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int WINDOW_FUTURE_DAYS = 180;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenRepositoryPort $repository the feed token repository port
   * @param CalendarFeedTokenSecretFactory $secretFactory the secret generator/hasher
   */
  public function __construct(
    private CalendarFeedTokenRepositoryPort $repository,
    private CalendarFeedTokenSecretFactory $secretFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ResolveCalendarFeedTokenQuery $query the query payload
   *
   * @throws CalendarFeedTokenNotFoundException when the secret matches no active token
   *
   * @return ResolveCalendarFeedTokenResult the resolved member identity and window bounds
   */
  public function __invoke(ResolveCalendarFeedTokenQuery $query): ResolveCalendarFeedTokenResult
  {
    $token = $this->repository->findActiveByTokenHash($this->secretFactory->hash($query->secret));
    if (null === $token) {
      throw new CalendarFeedTokenNotFoundException();
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if ($token->shouldRecordUsage($now)) {
      $token->recordUsage($now);
      $this->repository->save($token);
    }

    return new ResolveCalendarFeedTokenResult(
      organizationId: $token->organizationId(),
      userId: $token->userId(),
      from: $now->modify(sprintf('-%d days', self::WINDOW_PAST_DAYS))->format('Y-m-d\TH:i:sP'),
      to: $now->modify(sprintf('+%d days', self::WINDOW_FUTURE_DAYS))->format('Y-m-d\TH:i:sP'),
    );
  }
  // #endregion
}

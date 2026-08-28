<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Domain\Event\CalendarFeedTokenRevokedEvent;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use DateTimeImmutable;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase RevokeCalendarFeedTokenHandler.
 *
 * Members may always revoke their own token — no permission gate on
 * purpose: even a member who lost `organization.events.read` must be able
 * to kill their standing subscription. Scoped to the acting user's own
 * token by construction (the lookup key is the acting user id).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeCalendarFeedTokenHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenRepositoryPort $repository the feed token repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private CalendarFeedTokenRepositoryPort $repository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RevokeCalendarFeedTokenCommand $command the command payload
   *
   * @throws CalendarFeedTokenNotFoundException when the member has no active token
   *
   * @return RevokeCalendarFeedTokenResult the use case result
   */
  public function __invoke(RevokeCalendarFeedTokenCommand $command): RevokeCalendarFeedTokenResult
  {
    $token = $this->repository->findActiveByOrganizationAndUser($command->organizationId, $command->actorUserId);
    if (null === $token) {
      throw new CalendarFeedTokenNotFoundException();
    }

    $token->revoke();
    $this->repository->save($token);

    $this->eventDispatcher->dispatch(new CalendarFeedTokenRevokedEvent(
      organizationId: $command->organizationId,
      tokenId: (string) $token->id(),
      actorUserId: $command->actorUserId,
      reason: 'revoked',
    ));

    return new RevokeCalendarFeedTokenResult(
      tokenId: (string) $token->id(),
      revokedAt: $token->revokedAt() ?? new DateTimeImmutable(),
    );
  }
  // #endregion
}

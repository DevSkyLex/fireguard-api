<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use Calendar\Domain\Event\{CalendarFeedTokenCreatedEvent, CalendarFeedTokenRevokedEvent};
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase RotateCalendarFeedTokenHandler.
 *
 * Self-enforces `organization.events.read` — a member may only subscribe to
 * a feed they are allowed to read interactively. Creating and regenerating
 * are one operation: any previously active token is revoked first, so at
 * most one token per (organization, user) pair is ever live. The raw secret
 * exists only in the returned Result; the aggregate stores its SHA-256 hash.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RotateCalendarFeedTokenHandler implements CommandHandler
{
  // #region Constants
  private const string READ_PERMISSION = 'organization.events.read';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenRepositoryPort $repository the feed token repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param CalendarFeedTokenSecretFactory $secretFactory the secret generator/hasher
   * @param UuidFactory $uuidFactory the uuid factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private CalendarFeedTokenRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
    private CalendarFeedTokenSecretFactory $secretFactory,
    private UuidFactory $uuidFactory,
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
   * @param RotateCalendarFeedTokenCommand $command the command payload
   *
   * @return RotateCalendarFeedTokenResult the use case result, carrying the raw secret once
   */
  public function __invoke(RotateCalendarFeedTokenCommand $command): RotateCalendarFeedTokenResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      self::READ_PERMISSION,
    ]);

    $previous = $this->repository->findActiveByOrganizationAndUser($command->organizationId, $command->actorUserId);
    $rotated = null !== $previous;
    if (null !== $previous) {
      $previous->revoke();
      $this->repository->save($previous);
    }

    $secret = $this->secretFactory->generate();

    /** @var CalendarFeedTokenId $id */
    $id = $this->uuidFactory->create(CalendarFeedTokenId::class);

    $token = CalendarFeedToken::create(
      id: $id,
      organizationId: $command->organizationId,
      userId: $command->actorUserId,
      tokenHash: $this->secretFactory->hash($secret),
    );

    $this->repository->save($token);

    if (null !== $previous) {
      $this->eventDispatcher->dispatch(new CalendarFeedTokenRevokedEvent(
        organizationId: $command->organizationId,
        tokenId: (string) $previous->id(),
        actorUserId: $command->actorUserId,
        reason: 'rotated',
      ));
    }

    $this->eventDispatcher->dispatch(new CalendarFeedTokenCreatedEvent(
      organizationId: $command->organizationId,
      tokenId: (string) $id,
      actorUserId: $command->actorUserId,
      rotated: $rotated,
    ));

    return new RotateCalendarFeedTokenResult(
      secret: $secret,
      createdAt: $token->createdAt(),
      rotated: $rotated,
    );
  }
  // #endregion
}

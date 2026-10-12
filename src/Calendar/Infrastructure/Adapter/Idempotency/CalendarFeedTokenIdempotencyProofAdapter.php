<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\{RotateCalendarFeedTokenCommand, RotateCalendarFeedTokenResult};
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function hash;
use function hash_equals;
use function is_string;

/**
 * Class CalendarFeedTokenIdempotencyProofAdapter
 *
 * Rechecks the token's exact live rotation lineage, current account revision and current organization read permission before secret decryption.
 *
 * @category Adapter
 */
final readonly class CalendarFeedTokenIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * Method __construct
   *
   * @param Connection $connection explicit main owner database
   * @param UserSecurityRevisionPort $users current account and security revision
   * @param FreshOrganizationAuthorizationPort $organizations uncached main organization permission
   */
  public function __construct(private Connection $connection, private UserSecurityRevisionPort $users, private FreshOrganizationAuthorizationPort $organizations)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $commandClass): bool
  {
    return RotateCalendarFeedTokenCommand::class === $commandClass;
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof RotateCalendarFeedTokenCommand) {
      $this->deny();
    }
    $actor = $this->users->current($message->actorUserId);
    if (null === $actor || !$actor->active
      || !$this->organizations->hasPermission($message->actorUserId, $message->organizationId, 'organization.events.read')) {
      $this->deny();
    }
    $facts = [
      'calendarFeedOwner' => $message->actorUserId,
      'calendarFeedOrganization' => $message->organizationId,
      'calendarFeedSecurityRevision' => (string) $actor->securityRevision,
    ];
    if (null !== $storedProof) {
      foreach ($facts as $key => $value) {
        if (($storedProof->metadata[$key] ?? null) !== $value) {
          $this->deny();
        }
      }
      $row = $this->activeToken($message);
      if (false === $row || ($storedProof->metadata['calendarFeedTokenId'] ?? null) !== ($row['id'] ?? null)
        || ($storedProof->metadata['calendarFeedTokenHash'] ?? null) !== ($row['token_hash'] ?? null)) {
        $this->deny();
      }
      $facts = $storedProof->metadata;
    }

    return new SensitiveIdempotencyAuthorization($message->actorUserId, [
      'organizationId' => $message->organizationId,
      'actorUserId' => $message->actorUserId,
    ], $facts);
  }

  /**
   * {@inheritdoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof RotateCalendarFeedTokenCommand || !$result instanceof RotateCalendarFeedTokenResult) {
      $this->deny();
    }
    $facts = $this->authorize($message, null)->receiptProof;
    $row = $this->activeToken($message);
    if (false === $row || !is_string($row['id'] ?? null) || !is_string($row['token_hash'] ?? null)
      || !hash_equals($row['token_hash'], hash('sha256', $result->secret))) {
      $this->deny();
    }
    $facts['calendarFeedTokenId'] = $row['id'];
    $facts['calendarFeedTokenHash'] = $row['token_hash'];
    $facts['resourceId'] = $row['id'];
    $facts['resourceKind'] = 'calendar_feed_token';

    return $facts;
  }

  /**
   * {@inheritdoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof RotateCalendarFeedTokenResult || !hash_equals($proof->metadata['calendarFeedTokenHash'] ?? '', hash('sha256', $result->secret))) {
      $this->deny();
    }

    return $result;
  }

  /**
   * Method activeToken
   *
   * @param RotateCalendarFeedTokenCommand $command exact token owner
   *
   * @return array<string, mixed>|false fresh live token identifiers
   */
  private function activeToken(RotateCalendarFeedTokenCommand $command): array|false
  {
    return $this->connection->fetchAssociative(
      'SELECT id, token_hash FROM member_calendar_feed_tokens WHERE organization_id = :organization AND user_id = :user AND revoked_at IS NULL',
      ['organization' => $command->organizationId, 'user' => $command->actorUserId],
    );
  }

  /**
   * Method deny
   *
   * @return never denies before decoding the saved secret
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}

<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\Contract\Feed\CalendarFeedIcsIdentity;
use Calendar\Application\Port\Outbound\Feed\CalendarFeedIcsAccessPort;
use Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs\ReadCalendarFeedIcsCommand;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Shared\Application\Contract\Idempotency\{IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};

use function implode;

/**
 * Reauthorizes an opaque subscription scope before any original private feed bytes are decoded.
 *
 * @category Adapter
 */
final class CalendarFeedIcsIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  /**
   * @var array<string, string> original freshly authorized disclosure scope
   */
  private array $authorizedFacts = [];

  /**
   * @param CalendarFeedIcsAccessPort $access fresh owning subscription gate
   * @param IdempotencyFingerprintPort $fingerprint purpose-separated credential HMAC
   */
  public function __construct(private readonly CalendarFeedIcsAccessPort $access, private readonly IdempotencyFingerprintPort $fingerprint)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function supports(string $commandClass): bool
  {
    return ReadCalendarFeedIcsCommand::class === $commandClass;
  }

  /**
   * {@inheritDoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof ReadCalendarFeedIcsCommand) {
      throw new CalendarFeedTokenNotFoundException();
    }
    $facts = self::facts($this->access->resolve($message->secret));
    if (null !== $storedProof) {
      foreach ($facts as $key => $value) {
        if (($storedProof->metadata[$key] ?? null) !== $value) {
          throw new CalendarFeedTokenNotFoundException();
        }
      }
    }
    $this->authorizedFacts = $facts;
    $scope = $this->fingerprint->digest('calendar.feed.scope', $message->secret);

    return new SensitiveIdempotencyAuthorization('calendar-feed:' . $scope, ['feedScope' => $scope], $facts);
  }

  /**
   * {@inheritDoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof ReadCalendarFeedIcsCommand) {
      throw new CalendarFeedTokenNotFoundException();
    }
    $facts = self::facts($this->access->resolve($message->secret));
    if ([] !== $this->authorizedFacts && $this->authorizedFacts !== $facts) {
      throw new CalendarFeedTokenNotFoundException();
    }

    return $facts;
  }

  /**
   * {@inheritDoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    return $result;
  }

  /**
   * @param CalendarFeedIcsIdentity $identity exact freshly authorized source scope
   *
   * @return array<string, string> signed identifiers and effective visibility facts only
   */
  private static function facts(CalendarFeedIcsIdentity $identity): array
  {
    return ['scopeTenantId' => '', 'feed_token_id' => $identity->tokenId, 'organizationId' => $identity->organizationId, 'user_id' => $identity->userId, 'member_id' => $identity->memberId, 'security_revision' => (string) $identity->securityRevision, 'tenant_id' => $identity->tenantId ?? '', 'sources' => implode(',', $identity->sources)];
  }
}

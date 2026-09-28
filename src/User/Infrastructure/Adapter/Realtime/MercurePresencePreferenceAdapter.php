<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\{HubInterface, Update};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use User\Application\Contract\Presence\PresencePreferenceSubscription;
use User\Application\Port\Outbound\PresencePreferenceRealtimePort;

use function json_encode;
use function sprintf;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * Service MercurePresencePreferenceAdapter.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MercurePresencePreferenceAdapter implements PresencePreferenceRealtimePort
{
  /**
   * @since 1.0.0
   */
  public function __construct(
    private HubInterface $hub,
    private TokenFactoryInterface $defaultFactory,
    #[Autowire(value: '%env(default:mercure_subscriber_token_ttl:int:MERCURE_SUBSCRIBER_TOKEN_TTL)%')]
    private int $tokenTtl = 900,
  ) {
  }

  /**
   * @since 1.0.0
   */
  public function subscribe(string $id): PresencePreferenceSubscription
  {
    $topic = self::topic($id);
    $expiresAt = new DateTimeImmutable()->setTimestamp(time() + $this->tokenTtl);
    $token = $this->defaultFactory->create(subscribe: [$topic], publish: [], additionalClaims: ['exp' => $expiresAt]);

    return new PresencePreferenceSubscription($topic, $token, $expiresAt->format(DateTimeInterface::ATOM));
  }

  /**
   * @since 1.0.0
   */
  public function publish(string $id, bool $doNotDisturb, int $revision, bool $invisible = false): void
  {
    $this->hub->publish(new Update(topics: [self::topic($id)], data: json_encode(['type' => 'presence.preference.changed', 'doNotDisturb' => $doNotDisturb, 'revision' => $revision, 'invisible' => $invisible], JSON_THROW_ON_ERROR), private: true));
  }

  /**
   * @since 1.0.0
   */
  private static function topic(string $id): string
  {
    return sprintf('/users/%s/presence-preference', $id);
  }
}

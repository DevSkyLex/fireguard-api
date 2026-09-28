<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use DateTimeInterface;
use Messaging\Application\Contract\Presence\PresenceSubscription;
use Messaging\Application\Port\Outbound\PresenceRealtimePort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\{HubInterface, Update};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function json_encode;
use function sprintf;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * Service MercurePresenceAdapter.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MercurePresenceAdapter implements PresenceRealtimePort
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
  public function subscribe(string $id): PresenceSubscription
  {
    $topic = self::topic($id);
    $expiresAt = new DateTimeImmutable()->setTimestamp(time() + $this->tokenTtl);
    $token = $this->defaultFactory->create(subscribe: [$topic], publish: [], additionalClaims: ['exp' => $expiresAt]);

    return new PresenceSubscription($topic, $token, $expiresAt->format(DateTimeInterface::ATOM));
  }

  /**
   * @since 1.0.0
   */
  public function publish(string $id, string $memberId): void
  {
    $this->hub->publish(new Update(topics: [self::topic($id)], data: json_encode(['type' => 'presence.changed', 'organizationId' => $id, 'memberId' => $memberId], JSON_THROW_ON_ERROR), private: true));
  }

  /**
   * @since 1.0.0
   */
  private static function topic(string $id): string
  {
    return sprintf('/organizations/%s/presence', $id);
  }
}

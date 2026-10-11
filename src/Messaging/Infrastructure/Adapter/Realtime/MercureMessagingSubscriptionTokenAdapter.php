<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use Messaging\Application\Contract\Subscription\MessagingSubscription;
use Messaging\Application\Port\Outbound\MessagingSubscriptionTokenPort;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function sprintf;

/**
 * Class MercureMessagingSubscriptionTokenAdapter
 *
 * Owns Mercure claim types and preserves the existing exact topic and token lifetime.
 *
 * @category Adapter
 */
final readonly class MercureMessagingSubscriptionTokenAdapter implements MessagingSubscriptionTokenPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the configured default Mercure token factory and subscriber lifetime.
   *
   * @access public
   *
   * @param TokenFactoryInterface $defaultFactory configured Mercure JWT factory
   * @param int $tokenTtl subscriber lifetime in seconds
   *
   * @return void
   */
  public function __construct(private TokenFactoryInterface $defaultFactory, private int $tokenTtl)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Grants exactly one subscription topic and no publication capability.
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param string $conversationId authorized conversation
   *
   * @return MessagingSubscription scoped token and topic
   */
  public function create(string $organizationId, string $conversationId): MessagingSubscription
  {
    $topic = MercureMessagingRealtimePublisherAdapter::topic($organizationId, $conversationId);
    $token = $this->defaultFactory->create(subscribe: [$topic], publish: [], additionalClaims: ['exp' => new DateTimeImmutable(sprintf('+%d seconds', $this->tokenTtl))]);

    return new MessagingSubscription($token, $topic);
  }
  // #endregion
}

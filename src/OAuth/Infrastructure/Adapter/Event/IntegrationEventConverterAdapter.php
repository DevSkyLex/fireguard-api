<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Event;

use OAuth\Application\Contract\Event\Client\ClientLifecycleChangedEvent;
use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventSnapshotPort};
use Shared\Domain\Event\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Class IntegrationEventConverterAdapter
 *
 * Converts this owner's historical Domain snapshots into its published integration contracts.
 *
 * @category Adapter
 */
#[AutoconfigureTag('shared.integration_event_converter')]
final readonly class IntegrationEventConverterAdapter implements IntegrationEventConverterPort
{
  // #region Constants
  /**
   * Constant CONTRACTS
   *
   * Exact owner-local legacy classes supported by the event compatibility decoder.
   *
   * @var array<class-string, class-string>
   */
  private const array CONTRACTS = [
    'OAuth\Domain\Event\Consent\ConsentGrantedEvent' => \OAuth\Application\Contract\Event\Consent\ConsentGrantedEvent::class,
    'OAuth\Domain\Event\Token\TokenIssuedEvent' => \OAuth\Application\Contract\Event\Token\TokenIssuedEvent::class,
    'OAuth\Domain\Event\Token\TokenIssueFailedEvent' => \OAuth\Application\Contract\Event\Token\TokenIssueFailedEvent::class,
    'OAuth\Domain\Event\Token\TokenRefreshedEvent' => \OAuth\Application\Contract\Event\Token\TokenRefreshedEvent::class,
    'OAuth\Domain\Event\Token\TokenRefreshFailedEvent' => \OAuth\Application\Contract\Event\Token\TokenRefreshFailedEvent::class,
    'OAuth\Domain\Event\Token\TokenRevokedEvent' => \OAuth\Application\Contract\Event\Token\TokenRevokedEvent::class,
  ];

  /**
   * @var array<class-string, string> private domain types to stable public lifecycle names
   */
  private const array CLIENT_EVENTS = [
    'OAuth\Domain\Event\Client\ClientRegisteredEvent' => 'oauth.client.registered',
    'OAuth\Domain\Event\Client\ClientUpdatedEvent' => 'oauth.client.updated',
    'OAuth\Domain\Event\Client\ClientSecretRegeneratedEvent' => 'oauth.client.secret_regenerated',
    'OAuth\Domain\Event\Client\ClientActivatedEvent' => 'oauth.client.activated',
    'OAuth\Domain\Event\Client\ClientDeactivatedEvent' => 'oauth.client.deactivated',
    'OAuth\Domain\Event\Client\ClientDeletedEvent' => 'oauth.client.deleted',
  ];
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the technical copier without exposing legacy Domain types across module boundaries.
   *
   * @access public
   *
   * @param IntegrationEventSnapshotPort $snapshots copies persisted state without rerunning constructors
   *
   * @return void
   */
  public function __construct(private IntegrationEventSnapshotPort $snapshots)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method convert
   *
   * Preserves all snapshot properties, including the original occurrence time and event identity.
   *
   * @access public
   *
   * @param object $event an already deserialized legacy or current event
   *
   * @return ?object the public event, or null when this owner does not recognize the legacy class
   */
  public function convert(object $event): ?object
  {
    $type = self::CLIENT_EVENTS[$event::class] ?? null;
    if (null !== $type && $event instanceof DomainEvent) {
      return new ClientLifecycleChangedEvent($type, $event->eventId()->value, $event->aggregateId(), $event->occurredAt(), $event->payload());
    }
    $contract = self::CONTRACTS[$event::class] ?? null;

    return null === $contract ? null : $this->snapshots->copy($event, $contract);
  }
  // #endregion
}

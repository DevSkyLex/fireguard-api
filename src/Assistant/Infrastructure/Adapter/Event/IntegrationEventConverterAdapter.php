<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Event;

use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventSnapshotPort};
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
    'Assistant\Domain\Event\Message\AssistantQuestionAskedEvent' => \Assistant\Application\Contract\Event\Message\AssistantQuestionAskedEvent::class,
    'Assistant\Domain\Event\Message\AssistantReplyGeneratedEvent' => \Assistant\Application\Contract\Event\Message\AssistantReplyGeneratedEvent::class,
    'Assistant\Domain\Event\Thread\AssistantThreadStartedEvent' => \Assistant\Application\Contract\Event\Thread\AssistantThreadStartedEvent::class,
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
    $contract = self::CONTRACTS[$event::class] ?? null;

    return null === $contract ? null : $this->snapshots->copy($event, $contract);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Event;

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
    'Organization\Domain\Event\Invitation\OrganizationInvitationAcceptedEvent' => \Organization\Application\Contract\Event\Invitation\OrganizationInvitationAcceptedEvent::class,
    'Organization\Domain\Event\Invitation\OrganizationInvitationRevokedEvent' => \Organization\Application\Contract\Event\Invitation\OrganizationInvitationRevokedEvent::class,
    'Organization\Domain\Event\Invitation\OrganizationInvitationSentEvent' => \Organization\Application\Contract\Event\Invitation\OrganizationInvitationSentEvent::class,
    'Organization\Domain\Event\Join\OrganizationJoinChangedEvent' => \Organization\Application\Contract\Event\Join\OrganizationJoinChangedEvent::class,
    'Organization\Domain\Event\Member\OrganizationMemberAddedEvent' => \Organization\Application\Contract\Event\Member\OrganizationMemberAddedEvent::class,
    'Organization\Domain\Event\Member\OrganizationMemberRemovedEvent' => \Organization\Application\Contract\Event\Member\OrganizationMemberRemovedEvent::class,
    'Organization\Domain\Event\Organization\OrganizationArchivedEvent' => \Organization\Application\Contract\Event\Organization\OrganizationArchivedEvent::class,
    'Organization\Domain\Event\Organization\OrganizationCreatedEvent' => \Organization\Application\Contract\Event\Organization\OrganizationCreatedEvent::class,
    'Organization\Domain\Event\Organization\OrganizationOwnershipTransferredEvent' => \Organization\Application\Contract\Event\Organization\OrganizationOwnershipTransferredEvent::class,
    'Organization\Domain\Event\Organization\OrganizationRestoredEvent' => \Organization\Application\Contract\Event\Organization\OrganizationRestoredEvent::class,
    'Organization\Domain\Event\Organization\OrganizationSettingsUpdatedEvent' => \Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent::class,
    'Organization\Domain\Event\Organization\OrganizationSuspendedEvent' => \Organization\Application\Contract\Event\Organization\OrganizationSuspendedEvent::class,
    'Organization\Domain\Event\Plan\OrganizationPlanChangedEvent' => \Organization\Application\Contract\Event\Plan\OrganizationPlanChangedEvent::class,
    'Organization\Domain\Event\Role\OrganizationRoleAssignedEvent' => \Organization\Application\Contract\Event\Role\OrganizationRoleAssignedEvent::class,
    'Organization\Domain\Event\Role\OrganizationRoleCreatedEvent' => \Organization\Application\Contract\Event\Role\OrganizationRoleCreatedEvent::class,
    'Organization\Domain\Event\Role\OrganizationRoleDeletedEvent' => \Organization\Application\Contract\Event\Role\OrganizationRoleDeletedEvent::class,
    'Organization\Domain\Event\Role\OrganizationRoleUnassignedEvent' => \Organization\Application\Contract\Event\Role\OrganizationRoleUnassignedEvent::class,
    'Organization\Domain\Event\Role\OrganizationRoleUpdatedEvent' => \Organization\Application\Contract\Event\Role\OrganizationRoleUpdatedEvent::class,
    'Organization\Domain\Event\Security\OrganizationLastAdminLockoutPreventedEvent' => \Organization\Application\Contract\Event\Security\OrganizationLastAdminLockoutPreventedEvent::class,
    'Organization\Domain\Event\Security\OrganizationPermissionGrantDeniedEvent' => \Organization\Application\Contract\Event\Security\OrganizationPermissionGrantDeniedEvent::class,
    'Organization\Domain\Event\Team\TeamCreatedEvent' => \Organization\Application\Contract\Event\Team\TeamCreatedEvent::class,
    'Organization\Domain\Event\Team\TeamDeletedEvent' => \Organization\Application\Contract\Event\Team\TeamDeletedEvent::class,
    'Organization\Domain\Event\Team\TeamMemberAddedEvent' => \Organization\Application\Contract\Event\Team\TeamMemberAddedEvent::class,
    'Organization\Domain\Event\Team\TeamMemberRemovedEvent' => \Organization\Application\Contract\Event\Team\TeamMemberRemovedEvent::class,
    'Organization\Domain\Event\Team\TeamUpdatedEvent' => \Organization\Application\Contract\Event\Team\TeamUpdatedEvent::class,
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

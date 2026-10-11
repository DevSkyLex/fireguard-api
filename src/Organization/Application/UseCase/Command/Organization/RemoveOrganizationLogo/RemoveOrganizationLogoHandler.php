<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo;

use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Contract\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Application\Contract\Logo\OrganizationLogoStorageKey;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Domain\Exception\OrganizationArchivedException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileCleanupPort, TransactionManagerPort};

/**
 * Handler RemoveOrganizationLogoHandler.
 *
 * Clears the winning logo metadata and records idempotent deletion after commit.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationLogoHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the owning persistence capability
   * @param FileCleanupPort $fileCleanup the durable file cleanup
   * @param EventDispatcherPort $eventDispatcher the main transactional outbox
   * @param TransactionManagerPort $transactionManager the owning main transaction
   * @param OrganizationAuthorizationPort $authorization the contextual write permission
   */
  public function __construct(private OrganizationRepositoryPort $organizationRepository, private FileCleanupPort $fileCleanup, private EventDispatcherPort $eventDispatcher, private TransactionManagerPort $transactionManager, private OrganizationAuthorizationPort $authorization)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RemoveOrganizationLogoCommand $command the authenticated removal request
   *
   * @return RemoveOrganizationLogoResult the committed organization identity
   */
  public function __invoke(RemoveOrganizationLogoCommand $command): RemoveOrganizationLogoResult
  {
    if (!$this->authorization->hasPermission($command->actingUserId, $command->organizationId, 'organization.settings.write')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.settings.write');
    }

    return $this->transactionManager->transactional(function () use ($command): RemoveOrganizationLogoResult {
      $organizationId = OrganizationId::fromString($command->organizationId);
      $organization = $this->organizationRepository->findByIdForUpdate($organizationId);
      if (null === $organization) {
        throw OrganizationNotFoundException::withId($command->organizationId);
      }
      if ($organization->isArchived()) {
        throw OrganizationArchivedException::cannotRemoveLogo();
      }
      $previousUrl = $organization->logoUrl();
      if (null !== $previousUrl) {
        $organization->removeLogo();
        $this->organizationRepository->save($organization);
        $this->fileCleanup->request(OrganizationLogoStorageKey::fromUrl($command->organizationId, $previousUrl));
        $this->eventDispatcher->dispatch(new OrganizationSettingsUpdatedEvent($command->organizationId, ['logo']));
      }

      return new RemoveOrganizationLogoResult((string) $organizationId);
    });
  }
  // #endregion
}

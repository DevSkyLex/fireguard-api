<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo;

use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Event\Organization\OrganizationSettingsUpdatedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileStoragePort};

use function sprintf;

/**
 * UseCase RemoveOrganizationLogoHandler.
 *
 * Deletes the organization's stored logo and clears the canonical logo URL
 * on the aggregate. Idempotent when there is no logo to remove, mirroring
 * {@see \Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationHandler}'s
 * documented idempotence: a repeat call neither touches storage nor
 * re-dispatches an event.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationLogoHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant STORAGE_BASE.
   *
   * Base path inside file storage for organization logo files. Mirrors
   * {@see \Organization\Infrastructure\Image\OrganizationLogoResizer::pathFor()}
   * exactly — duplicated rather than imported because that class lives in
   * Infrastructure, which the Application layer may not depend on. Keep the
   * two in sync if the storage layout ever changes.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string STORAGE_BASE = 'organization-logos';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationLogoHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param FileStoragePort $fileStorage the file storage port used to delete the stored logo
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private FileStoragePort $fileStorage,
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
   * @param RemoveOrganizationLogoCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationArchivedException when the organization is archived
   *
   * @return RemoveOrganizationLogoResult the use case result
   */
  public function __invoke(RemoveOrganizationLogoCommand $command): RemoveOrganizationLogoResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    if ($organization->isArchived()) {
      throw OrganizationArchivedException::cannotRemoveLogo();
    }

    if (null !== $organization->logoUrl()) {
      $path = self::pathFor($command->organizationId);

      if ($this->fileStorage->exists($path)) {
        $this->fileStorage->delete($path);
      }

      $organization->removeLogo();
      $this->organizationRepository->save($organization);

      $this->eventDispatcher->dispatch(new OrganizationSettingsUpdatedEvent(
        organizationId: $command->organizationId,
        changedFields: ['logo'],
      ));
    }

    return new RemoveOrganizationLogoResult(
      organizationId: (string) $organizationId,
    );
  }

  /**
   * Method pathFor.
   *
   * Builds the storage path of an organization logo. Mirrors
   * {@see \Organization\Infrastructure\Image\OrganizationLogoResizer::pathFor()}.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return string the storage path
   */
  private static function pathFor(string $organizationId): string
  {
    return sprintf('%s/%s/logo.webp', self::STORAGE_BASE, $organizationId);
  }
  // #endregion
}

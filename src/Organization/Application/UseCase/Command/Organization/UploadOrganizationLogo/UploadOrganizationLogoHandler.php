<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo;

use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Contract\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Application\Contract\Logo\OrganizationLogoStorageKey;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Application\Port\Outbound\OrganizationLogoImagePort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileCleanupPort, FileStoragePort, LoggerPort, RootTransactionManagerPort, UuidGeneratorPort};
use Throwable;

use function rtrim;
use function sprintf;

/**
 * Handler UploadOrganizationLogoHandler.
 *
 * Commits immutable upload identity and deferred replacement cleanup under a fresh row lock.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadOrganizationLogoHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizations the owning persistence capability
   * @param OrganizationAuthorizationPort $authorization the contextual write permission
   * @param OrganizationLogoImagePort $images the bounded image encoder
   * @param FileStoragePort $files the immutable file storage
   * @param FileCleanupPort $cleanup the transactional replacement cleanup
   * @param RootTransactionManagerPort $transactions the final main commit boundary
   * @param EventDispatcherPort $events the transactional main outbox
   * @param UuidGeneratorPort $ids the upload attempt identity generator
   * @param LoggerPort $logger the compensation failure logger
   */
  public function __construct(private OrganizationRepositoryPort $organizations, private OrganizationAuthorizationPort $authorization, private OrganizationLogoImagePort $images, private FileStoragePort $files, private FileCleanupPort $cleanup, private RootTransactionManagerPort $transactions, private EventDispatcherPort $events, private UuidGeneratorPort $ids, private LoggerPort $logger)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param UploadOrganizationLogoCommand $command the authorized upload request
   *
   * @return UploadOrganizationLogoResult the committed logo identity
   */
  public function __invoke(UploadOrganizationLogoCommand $command): UploadOrganizationLogoResult
  {
    if (!$this->authorization->hasPermission($command->actingUserId, $command->organizationId, 'organization.settings.write')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.settings.write');
    }
    $this->transactions->assertCanStart();
    $attemptId = $this->ids->generate();
    $path = OrganizationLogoStorageKey::forAttempt($command->organizationId, $attemptId);

    try {
      return $this->transactions->transactional(function () use ($command, $path, $attemptId): UploadOrganizationLogoResult {
        $organization = $this->organizations->findByIdForUpdate(OrganizationId::fromString($command->organizationId));
        if (null === $organization) {
          throw OrganizationNotFoundException::withId($command->organizationId);
        }
        $previousUrl = $organization->logoUrl();
        $contents = $this->images->encode($command->contents);
        $this->files->write($path, $contents);
        $url = sprintf('%s/api/organizations/%s/logo.webp?v=%s', rtrim($command->publicBaseUrl, '/'), $command->organizationId, $attemptId);
        $organization->setLogoUrl($url);
        $this->organizations->save($organization);
        if (null !== $previousUrl) {
          $this->cleanup->request(OrganizationLogoStorageKey::fromUrl($command->organizationId, $previousUrl));
        }
        $this->events->dispatch(new OrganizationSettingsUpdatedEvent($command->organizationId, ['logo']));

        return new UploadOrganizationLogoResult($command->organizationId, $url);
      });
    } catch (CommitOutcomeUnknownException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      try {
        if ($this->files->exists($path)) {
          $this->files->delete($path);
        }
      } catch (Throwable $cleanupFailure) {
        try {
          $this->logger->warning('Rolled-back organization logo cleanup failed.', ['organizationId' => $command->organizationId, 'storagePath' => $path, 'error' => $cleanupFailure->getMessage()]);
        } catch (Throwable) {
          // Diagnostic failure cannot replace the original transaction failure.
        }
      }

      throw $exception;
    }
  }
  // #endregion
}

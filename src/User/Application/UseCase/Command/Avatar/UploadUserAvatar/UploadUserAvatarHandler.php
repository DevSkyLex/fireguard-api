<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Avatar\UploadUserAvatar;

use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, LoggerPort, RootTransactionManagerPort, UuidGeneratorPort};
use Throwable;
use User\Application\Contract\User\{AvatarPaths, AvatarVariants};
use User\Application\Port\Outbound\{AvatarPersistencePort, AvatarStoragePort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\ValueObject\{UserId, UserProfile};

use function sprintf;

/** Owns immutable variants, the final auth commit and durable obsolete-file cleanup.
 * @category Handler
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadUserAvatarHandler implements CommandHandler
{
  public function __construct(
    private UserRepositoryPort $users,
    private AvatarStoragePort $storage,
    private AvatarPersistencePort $avatars,
    private RootTransactionManagerPort $transactions,
    private FileCleanupPort $cleanup,
    private UuidGeneratorPort $ids,
    private LoggerPort $logger,
  ) {
  }

  public function __invoke(UploadUserAvatarCommand $command): UploadUserAvatarResult
  {
    $this->transactions->assertCanStart();
    $userId = new UserId($command->userId);
    if (null === $this->users->findById($userId)) {
      throw UserNotFoundException::withId($command->userId);
    }
    $generation = $this->ids->generate();

    try {
      $this->storage->resize($command->userId, $command->sourceContents, $generation);

      return $this->transactions->transactional(fn (): UploadUserAvatarResult => $this->avatars->withUserLock(
        $command->userId,
        fn (): UploadUserAvatarResult => $this->persistAvatar($command, $userId, $generation),
      ));
    } catch (Throwable $failure) {
      if (!$failure instanceof CommitOutcomeUnknownException) {
        $this->compensateUpload($command->userId, $generation);
      }

      throw $failure;
    }
  }

  /**
   * Method persistAvatar.
   *
   * Saves the fresh account and pointer with obsolete-file requests under its owning lock.
   *
   * @access private
   * @since 1.1.0
   *
   * @param UploadUserAvatarCommand $command the upload command
   * @param UserId $userId the validated account identifier
   * @param string $generation the immutable attempt generation
   *
   * @return UploadUserAvatarResult the saved avatar outcome
   */
  private function persistAvatar(UploadUserAvatarCommand $command, UserId $userId, string $generation): UploadUserAvatarResult
  {
    $user = $this->users->findById($userId);
    if (null === $user) {
      throw UserNotFoundException::withId($command->userId);
    }
    $previous = $this->avatars->generation($command->userId);
    $profile = $user->profile();
    $user->updateProfile(new UserProfile(
      firstName: $profile->firstName,
      lastName: $profile->lastName,
      avatarUrl: sprintf('%s/api/users/%s/avatar/%d.webp?v=%d', $command->baseUrl, $command->userId, AvatarVariants::SIZES[0], $command->version),
    ));
    $this->users->save($user);
    $this->avatars->replace($command->userId, $generation);
    foreach (AvatarPaths::variants($command->userId, $previous) as $path) {
      $this->cleanup->request($path);
    }

    return new UploadUserAvatarResult($command->userId);
  }

  /**
   * Method compensateUpload.
   *
   * Deletes only the failed attempt while preserving its original error.
   *
   * @access private
   * @since 1.1.0
   *
   * @param string $userId the account identifier
   * @param string $generation the immutable attempt generation
   *
   * @return void No return value
   */
  private function compensateUpload(string $userId, string $generation): void
  {
    try {
      $this->storage->delete($userId, $generation);
    } catch (Throwable $cleanupFailure) {
      try {
        $this->logger->error('Avatar compensation failed.', ['userId' => $userId, 'generation' => $generation, 'failureClass' => $cleanupFailure::class]);
      } catch (Throwable) {
        // Logging must never replace the original storage or SQL failure.
      }
    }
  }
}

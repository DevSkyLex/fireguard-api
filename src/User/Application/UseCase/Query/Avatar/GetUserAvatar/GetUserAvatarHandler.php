<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\Avatar\GetUserAvatar;

use Shared\Application\Contract\Storage\FileStorageException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use User\Application\Contract\User\{AvatarPaths, AvatarVariants};
use User\Application\Port\Outbound\AvatarPersistencePort;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function abs;

/**
 * Owns the public avatar lookup independently of an authenticated actor.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserAvatarHandler implements QueryHandler
{
  private const array SIZES = AvatarVariants::SIZES;

  public function __construct(private UserRepositoryPort $users, private FileStoragePort $storage, private AvatarPersistencePort $avatars)
  {
  }

  public function __invoke(GetUserAvatarQuery $query): GetUserAvatarResult
  {
    if (null === $this->users->findById(new UserId($query->userId))) {
      return new GetUserAvatarResult(null, 'User not found.');
    }
    $size = self::SIZES[0];
    foreach (self::SIZES as $candidate) {
      if (abs($query->requestedSize - $candidate) < abs($query->requestedSize - $size)) {
        $size = $candidate;
      }
    }

    try {
      return new GetUserAvatarResult($this->storage->read(AvatarPaths::variant($query->userId, $this->avatars->generation($query->userId), $size)));
    } catch (FileStorageException) {
      return new GetUserAvatarResult(null, 'Avatar not found.');
    }
  }
}

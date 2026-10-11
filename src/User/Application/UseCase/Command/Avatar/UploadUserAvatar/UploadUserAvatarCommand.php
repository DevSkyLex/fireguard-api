<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\Avatar\UploadUserAvatar;

use Shared\Application\Message\CommandMessage;

/**
 * Stores validated multipart bytes and updates the owned avatar URL.
 *
 * @category Command
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadUserAvatarCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $sourceContents, public string $baseUrl, public int $version)
  {
  }
}

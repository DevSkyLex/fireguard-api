<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Shared\Application\Contract\Storage\FileStorageException as PublicFileStorageException;

/**
 * Class FileStorageException
 *
 * Preserves the historical storage exception name while exposing failures through the pure storage contract.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FileStorageException extends PublicFileStorageException
{
}

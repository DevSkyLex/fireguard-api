<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

/**
 * Validates, resizes and stores owned avatar image variants.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AvatarStoragePort
{
  public function resize(string $userId, string $sourceContents, string $generation): void;

  public function delete(string $userId, string $generation): void;
}

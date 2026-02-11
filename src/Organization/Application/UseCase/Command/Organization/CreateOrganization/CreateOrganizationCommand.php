<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganization;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateOrganizationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateOrganizationCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $name,
    public string $ownerUserId,
    public ?string $slug = null,
  ) {
  }
  // #endregion
}

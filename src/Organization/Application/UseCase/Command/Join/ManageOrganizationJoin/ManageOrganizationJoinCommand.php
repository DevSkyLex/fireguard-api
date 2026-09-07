<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Join\ManageOrganizationJoin;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ManageOrganizationJoinCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ManageOrganizationJoinCommand implements CommandMessage
{
  /**
   * @since 1.0.0
   *
   * @param string $operation explicit use case operation
   * @param string $userId authenticated actor
   * @param ?string $organizationId organization scope
   * @param ?string $resourceId request/domain/invitation id
   * @param ?string $mode policy mode
   * @param ?string $roleId automatic role
   * @param ?string $domain submitted domain
   * @param list<string> $roleIds approval roles
   */
  public function __construct(public string $operation, public string $userId, public ?string $organizationId = null, public ?string $resourceId = null, public ?string $mode = null, public ?string $roleId = null, public ?string $domain = null, public array $roleIds = [])
  {
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests;

use Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests\ExpireStaleApprovalRequestsCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\CommandMessage;

/**
 * Test ExpireStaleApprovalRequestsCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExpireStaleApprovalRequestsCommand::class)]
final class ExpireStaleApprovalRequestsCommandTest extends TestCase
{
  #[Test]
  public function testIsAPayloadFreeCommandMessage(): void
  {
    $command = new ExpireStaleApprovalRequestsCommand();

    self::assertInstanceOf(CommandMessage::class, $command);
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\RejectApprovalRequest;

use Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\RejectApprovalRequestCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RejectApprovalRequestCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RejectApprovalRequestCommand::class)]
final class RejectApprovalRequestCommandTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $command = new RejectApprovalRequestCommand('org-1', 'req-1', 'user-1', 'denied');

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('req-1', $command->requestId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('denied', $command->decisionNote);
  }

  #[Test]
  public function testDecisionNoteDefaultsToNull(): void
  {
    $command = new RejectApprovalRequestCommand('org-1', 'req-1', 'user-1');

    self::assertNull($command->decisionNote);
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest;

use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApproveApprovalRequestCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApproveApprovalRequestCommand::class)]
final class ApproveApprovalRequestCommandTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $command = new ApproveApprovalRequestCommand('org-1', 'req-1', 'user-1', 'looks good');

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('req-1', $command->requestId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('looks good', $command->decisionNote);
  }

  #[Test]
  public function testDecisionNoteDefaultsToNull(): void
  {
    $command = new ApproveApprovalRequestCommand('org-1', 'req-1', 'user-1');

    self::assertNull($command->decisionNote);
  }
}

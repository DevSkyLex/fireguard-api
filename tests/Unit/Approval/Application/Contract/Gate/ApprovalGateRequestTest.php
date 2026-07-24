<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Gate;

use Approval\Application\Contract\Gate\ApprovalGateRequest;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalGateRequest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalGateRequest::class)]
final class ApprovalGateRequestTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $request = new ApprovalGateRequest(
      organizationId: 'org-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByUserId: 'user-1',
      payload: ['nonConformityId' => 'nc-1'],
    );

    self::assertSame('org-1', $request->organizationId);
    self::assertSame('nc_waiver', $request->actionType);
    self::assertSame('nc-1', $request->subjectId);
    self::assertSame('user-1', $request->requestedByUserId);
    self::assertSame(['nonConformityId' => 'nc-1'], $request->payload);
  }

  #[Test]
  public function testPayloadDefaultsToEmptyArray(): void
  {
    $request = new ApprovalGateRequest('org-1', 'nc_waiver', 'nc-1', 'user-1');

    self::assertSame([], $request->payload);
  }
}

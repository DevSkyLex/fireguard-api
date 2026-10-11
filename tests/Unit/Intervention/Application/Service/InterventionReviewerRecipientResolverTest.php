<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Service\InterventionReviewerRecipientResolver;
use Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

final class InterventionReviewerRecipientResolverTest extends TestCase
{
  /**
   * @return iterable<string, array{list<string>}>
   */
  public static function recipients(): iterable
  {
    yield 'qualified recipients' => [['reviewer', 'wildcard-reviewer']];
    yield 'none qualify' => [[]];
  }

  /**
   * @param list<string> $userIds
   */
  #[Test]
  #[DataProvider('recipients')]
  public function resolvesThroughTheGroupedOwnerCapability(array $userIds): void
  {
    $members = $this->createMock(OrganizationMemberNotificationDirectoryPort::class);
    $members->expects(self::once())->method('administratorUserIds')->with('organization', 'organization.interventions.review')->willReturn($userIds);
    self::assertSame($userIds, new InterventionReviewerRecipientResolver($members)->organizationReviewers('organization'));
  }
}

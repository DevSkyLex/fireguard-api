<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\InterventionReviewerRecipientResolver;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionReviewerRecipientResolver.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionReviewerRecipientResolver::class)]
final class InterventionReviewerRecipientResolverTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testOrganizationReviewersReturnsActiveMembersGrantingTheReviewPermission(): void
  {
    $reviewer = $this->member('550e8400-e29b-41d4-a716-446655440111', 'user-reviewer', true);
    $inactiveReviewer = $this->member('550e8400-e29b-41d4-a716-446655440112', 'user-inactive', false);
    $regularMember = $this->member('550e8400-e29b-41d4-a716-446655440113', 'user-regular', true);
    $wildcardReviewer = $this->member('550e8400-e29b-41d4-a716-446655440114', 'user-wildcard', true);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$reviewer, $inactiveReviewer, $regularMember, $wildcardReviewer]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturnMap([
      ['user-reviewer', self::ORG_ID, ['organization.interventions.review']],
      ['user-inactive', self::ORG_ID, ['organization.interventions.review']],
      ['user-regular', self::ORG_ID, ['organization.interventions.execute']],
      ['user-wildcard', self::ORG_ID, ['organization.interventions.*']],
    ]);

    $resolver = new InterventionReviewerRecipientResolver($members, $authorization);

    self::assertSame(['user-reviewer', 'user-wildcard'], $resolver->organizationReviewers(self::ORG_ID));
  }

  #[Test]
  public function testOrganizationReviewersDeduplicatesRepeatedUserIds(): void
  {
    $first = $this->member('550e8400-e29b-41d4-a716-446655440121', 'user-dup', true);
    $second = $this->member('550e8400-e29b-41d4-a716-446655440122', 'user-dup', true);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$first, $second]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['*']);

    $resolver = new InterventionReviewerRecipientResolver($members, $authorization);

    self::assertSame(['user-dup'], $resolver->organizationReviewers(self::ORG_ID));
  }

  #[Test]
  public function testOrganizationReviewersReturnsEmptyListWhenNoneQualify(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([
      $this->member('550e8400-e29b-41d4-a716-446655440131', 'user-1', true),
    ]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.interventions.execute']);

    $resolver = new InterventionReviewerRecipientResolver($members, $authorization);

    self::assertSame([], $resolver->organizationReviewers(self::ORG_ID));
  }

  private function member(string $memberId, string $userId, bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString($memberId),
      OrganizationId::fromString(self::ORG_ID),
      $userId,
      $isActive,
      new DateTimeImmutable(),
    );
  }
}

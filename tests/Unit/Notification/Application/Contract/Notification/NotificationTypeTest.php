<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\Contract\Notification;

use Notification\Application\Contract\Notification\NotificationType;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_unique;
use function count;
use function in_array;

/**
 * Test NotificationTypeTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NotificationType::class)]
final class NotificationTypeTest extends TestCase
{
  #[Test]
  public function testAllReturnsEveryKnownTypeConstantWithoutDuplicates(): void
  {
    $all = NotificationType::all();

    self::assertContains(NotificationType::SYSTEM_ANNOUNCEMENT, $all);
    self::assertContains(NotificationType::SYSTEM_MAINTENANCE, $all);
    self::assertContains(NotificationType::ORGANIZATION_INVITATION, $all);
    self::assertContains(NotificationType::ORGANIZATION_INVITATION_ACCEPTED, $all);
    self::assertContains(NotificationType::ORGANIZATION_INVITATION_REVOKED, $all);
    self::assertContains(NotificationType::ORGANIZATION_MEMBER_JOINED, $all);
    self::assertContains(NotificationType::ORGANIZATION_MEMBER_ADDED, $all);
    self::assertContains(NotificationType::ORGANIZATION_MEMBER_REMOVED, $all);
    self::assertContains(NotificationType::ORGANIZATION_PLAN_OVER_QUOTA, $all);
    self::assertContains(NotificationType::USER_EMAIL_VERIFIED, $all);
    self::assertContains(NotificationType::FACILITY_ARCHIVED, $all);
    self::assertContains(NotificationType::EQUIPMENT_UNDER_MAINTENANCE, $all);

    self::assertCount(12, $all);
    self::assertSame(count($all), count(array_unique($all)));
  }

  /**
   * @param string $type a known type constant
   */
  #[Test]
  #[DataProvider('knownTypeProvider')]
  public function testIsValidReturnsTrueForEveryKnownType(string $type): void
  {
    self::assertTrue(NotificationType::isValid($type));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function knownTypeProvider(): iterable
  {
    yield 'system announcement' => [NotificationType::SYSTEM_ANNOUNCEMENT];
    yield 'system maintenance' => [NotificationType::SYSTEM_MAINTENANCE];
    yield 'organization invitation' => [NotificationType::ORGANIZATION_INVITATION];
    yield 'organization invitation accepted' => [NotificationType::ORGANIZATION_INVITATION_ACCEPTED];
    yield 'organization invitation revoked' => [NotificationType::ORGANIZATION_INVITATION_REVOKED];
    yield 'organization member joined' => [NotificationType::ORGANIZATION_MEMBER_JOINED];
    yield 'organization member added' => [NotificationType::ORGANIZATION_MEMBER_ADDED];
    yield 'organization member removed' => [NotificationType::ORGANIZATION_MEMBER_REMOVED];
    yield 'organization plan over quota' => [NotificationType::ORGANIZATION_PLAN_OVER_QUOTA];
    yield 'user email verified' => [NotificationType::USER_EMAIL_VERIFIED];
    yield 'facility archived' => [NotificationType::FACILITY_ARCHIVED];
    yield 'equipment under maintenance' => [NotificationType::EQUIPMENT_UNDER_MAINTENANCE];
  }

  #[Test]
  public function testIsValidReturnsFalseForAnUnknownType(): void
  {
    self::assertFalse(NotificationType::isValid('organization.unknown_event'));
  }

  #[Test]
  public function testIsValidReturnsFalseForAnEmptyString(): void
  {
    self::assertFalse(NotificationType::isValid(''));
  }

  #[Test]
  public function testIsValidReturnsFalseWhenCaseDoesNotMatchExactly(): void
  {
    self::assertFalse(NotificationType::isValid('ORGANIZATION.INVITATION'));
  }

  #[Test]
  public function testCategoryReturnsTheSegmentBeforeTheFirstDot(): void
  {
    self::assertSame(
      NotificationType::CATEGORY_ORGANIZATION,
      NotificationType::category(NotificationType::ORGANIZATION_INVITATION),
    );
    self::assertSame(
      NotificationType::CATEGORY_SYSTEM,
      NotificationType::category(NotificationType::SYSTEM_ANNOUNCEMENT),
    );
    self::assertSame(
      NotificationType::CATEGORY_EQUIPMENT,
      NotificationType::category(NotificationType::EQUIPMENT_UNDER_MAINTENANCE),
    );
  }

  #[Test]
  public function testCategoryKeepsOnlyTheFirstSegmentWhenSeveralDotsArePresent(): void
  {
    self::assertSame('organization', NotificationType::category('organization.invitation.accepted'));
  }

  #[Test]
  public function testCategoryFallsBackToTheFullStringWhenNoDotIsPresent(): void
  {
    self::assertSame('legacy', NotificationType::category('legacy'));
  }

  #[Test]
  public function testCategoryReturnsAnEmptyCategoryForALeadingDot(): void
  {
    self::assertSame('', NotificationType::category('.orphan'));
  }

  #[Test]
  public function testEveryKnownTypeExposesACategoryMatchingItsPrefix(): void
  {
    foreach (NotificationType::all() as $type) {
      $category = NotificationType::category($type);

      self::assertNotSame('', $category);
      self::assertTrue(
        in_array($category, [
          NotificationType::CATEGORY_SYSTEM,
          NotificationType::CATEGORY_ORGANIZATION,
          NotificationType::CATEGORY_USER,
          NotificationType::CATEGORY_FACILITY,
          NotificationType::CATEGORY_EQUIPMENT,
        ], true),
      );
    }
  }
}

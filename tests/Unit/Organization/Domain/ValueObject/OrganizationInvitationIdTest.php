<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationInvitationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationInvitationIdTest.
 *
 * The invitation identifier is what an invitee's link resolves against, so the
 * named constructor must keep the inherited UUID validation instead of
 * accepting whatever string a URL happens to carry.
 *
 * @category ValueObject Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationInvitationId::class)]
final class OrganizationInvitationIdTest extends TestCase
{
  // #region Constants
  private const string VALID_UUID = '550e8400-e29b-41d4-a716-446655480010';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromStringBuildsTheIdentifierAndKeepsItsValue(): void
  {
    $id = OrganizationInvitationId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function testFromStringRejectsAValueThatIsNotAUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    OrganizationInvitationId::fromString('not-a-uuid');
  }
  // #endregion
}

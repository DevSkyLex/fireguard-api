<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationRoleName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationRoleName.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRoleName::class)]
final class OrganizationRoleNameTest extends TestCase
{
  #[Test]
  public function testAcceptsValidRoleName(): void
  {
    $name = new OrganizationRoleName('site_manager');

    self::assertSame('site_manager', $name->value);
    self::assertSame('site_manager', (string) $name);
  }

  #[Test]
  public function testRejectsUppercase(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRoleName('SiteManager');
  }

  #[Test]
  public function testRejectsTooShortValue(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRoleName('ab');
  }

  #[Test]
  public function testEqualsComparesValue(): void
  {
    $left = new OrganizationRoleName('member');
    $right = new OrganizationRoleName('member');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals(new OrganizationRoleName('admin')));
  }
}

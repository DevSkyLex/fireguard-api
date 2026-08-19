<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationStatus::class)]
final class OrganizationStatusTest extends TestCase
{
  #[Test]
  public function testFromIsActiveMapsToStatus(): void
  {
    self::assertSame(OrganizationStatus::ACTIVE, OrganizationStatus::fromIsActive(true));
    self::assertSame(OrganizationStatus::SUSPENDED, OrganizationStatus::fromIsActive(false));
  }

  #[Test]
  public function testIsActiveOnlyForActiveCase(): void
  {
    self::assertTrue(OrganizationStatus::ACTIVE->isActive());
    self::assertFalse(OrganizationStatus::SUSPENDED->isActive());
    self::assertFalse(OrganizationStatus::ARCHIVED->isActive());
  }
}

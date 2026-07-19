<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationLegalType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(OrganizationLegalType::class)]
final class OrganizationLegalTypeTest extends TestCase
{
  #[Test]
  public function testValuesMatchesEnumCases(): void
  {
    $expected = [];
    foreach (OrganizationLegalType::cases() as $case) {
      $expected[] = $case->value;
    }

    self::assertSame($expected, OrganizationLegalType::values());
  }

  #[Test]
  public function testFromRejectsUnknownValue(): void
  {
    $this->expectException(ValueError::class);

    OrganizationLegalType::from('unknown_type');
  }
}

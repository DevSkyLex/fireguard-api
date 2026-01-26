<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\ValueObject;

use Authorization\Domain\ValueObject\SubjectType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SubjectTypeTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SubjectType::class)]
final class SubjectTypeTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testLabelReturnsExpectedValue(): void
  {
    self::assertSame('User', SubjectType::USER->label());
  }
  // #endregion
}

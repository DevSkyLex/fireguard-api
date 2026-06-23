<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\ValueObject\Locale;
use ValueError;

/**
 * Test LocaleTest.
 *
 * @category ValueObject Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Locale::class)]
final class LocaleTest extends TestCase
{
  // #region Methods
  /**
   * Method testFromResolvesSupportedValues.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testFromResolvesSupportedValues(): void
  {
    self::assertSame(Locale::SYSTEM, Locale::from('system'));
    self::assertSame(Locale::EN, Locale::from('en'));
    self::assertSame(Locale::FR, Locale::from('fr'));
    self::assertSame(Locale::ES, Locale::from('es'));
  }

  /**
   * Method testFromRejectsUnsupportedValue.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testFromRejectsUnsupportedValue(): void
  {
    $this->expectException(ValueError::class);

    Locale::from('de');
  }

  /**
   * Method testValuesMatchCases.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testValuesMatchCases(): void
  {
    self::assertSame(['system', 'en', 'fr', 'es'], Locale::VALUES);
  }

  /**
   * Method testLabelReturnsEndonym.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testLabelReturnsEndonym(): void
  {
    self::assertSame('System', Locale::SYSTEM->label());
    self::assertSame('English', Locale::EN->label());
    self::assertSame('Français', Locale::FR->label());
    self::assertSame('Español', Locale::ES->label());
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationRegionalSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationRegionalSettings.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRegionalSettings::class)]
final class OrganizationRegionalSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsAreValid(): void
  {
    $settings = new OrganizationRegionalSettings();

    self::assertSame('UTC', $settings->timezone);
    self::assertSame('en-US', $settings->locale);
    self::assertSame('yyyy-MM-dd', $settings->dateFormat);
    self::assertSame('monday', $settings->firstDayOfWeek);
    self::assertSame('metric', $settings->measurementSystem);
  }

  #[Test]
  public function testTrimsTimezone(): void
  {
    $settings = new OrganizationRegionalSettings(timezone: '  Europe/Paris  ');

    self::assertSame('Europe/Paris', $settings->timezone);
  }

  #[Test]
  public function testRejectsUnsupportedTimezone(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegionalSettings(timezone: 'Mars/Olympus');
  }

  #[Test]
  public function testRejectsUnsupportedLocale(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegionalSettings(locale: 'xx-XX');
  }

  #[Test]
  public function testFromArrayFallsBackToDefaults(): void
  {
    $settings = OrganizationRegionalSettings::fromArray(['locale' => 'fr-FR']);

    self::assertSame('fr-FR', $settings->locale);
    self::assertSame('UTC', $settings->timezone);
  }

  #[Test]
  public function testToArrayFromArrayRoundTrip(): void
  {
    $original = new OrganizationRegionalSettings(
      timezone: 'Europe/Paris',
      locale: 'fr-FR',
      dateFormat: 'dd/MM/yyyy',
      firstDayOfWeek: 'sunday',
      measurementSystem: 'imperial',
    );

    $restored = OrganizationRegionalSettings::fromArray($original->toArray());

    self::assertEquals($original, $restored);
  }
}

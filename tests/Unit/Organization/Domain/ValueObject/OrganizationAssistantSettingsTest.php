<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\Catalog\OrganizationAssistantDefaults;
use Organization\Domain\ValueObject\OrganizationAssistantSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationAssistantSettingsTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAssistantSettings::class)]
#[CoversClass(OrganizationAssistantDefaults::class)]
final class OrganizationAssistantSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsAreOptOut(): void
  {
    $settings = new OrganizationAssistantSettings();

    self::assertFalse($settings->enabled, 'The assistant must be opt-in: enabling it sends conversation content to the inference backend.');
    self::assertNull($settings->model);
    self::assertSame(OrganizationAssistantDefaults::TEMPERATURE, $settings->temperature);
    self::assertTrue($settings->includeBusinessContext);
  }

  #[Test]
  public function testConstructorRejectsTemperatureOutOfBounds(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationAssistantSettings(temperature: OrganizationAssistantDefaults::MAX_TEMPERATURE + 0.1);
  }

  #[Test]
  public function testConstructorRejectsNegativeTemperature(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationAssistantSettings(temperature: -0.1);
  }

  #[Test]
  public function testBlankModelCollapsesToTheOperatorDefault(): void
  {
    self::assertNull(new OrganizationAssistantSettings(model: '   ')->model);
    self::assertSame('llama3.1:8b', new OrganizationAssistantSettings(model: '  llama3.1:8b  ')->model);
  }

  #[Test]
  public function testToArrayRoundTripsThroughFromArray(): void
  {
    $settings = new OrganizationAssistantSettings(
      enabled: true,
      model: 'mistral:7b',
      temperature: 0.7,
      includeBusinessContext: false,
    );

    $restored = OrganizationAssistantSettings::fromArray($settings->toArray());

    self::assertTrue($restored->enabled);
    self::assertSame('mistral:7b', $restored->model);
    self::assertSame(0.7, $restored->temperature);
    self::assertFalse($restored->includeBusinessContext);
  }

  #[Test]
  public function testFromArrayFallsBackToDefaultsOnMalformedData(): void
  {
    $settings = OrganizationAssistantSettings::fromArray([
      'model' => ['not', 'a', 'string'],
      'temperature' => 'hot',
    ]);

    self::assertFalse($settings->enabled);
    self::assertNull($settings->model);
    self::assertSame(OrganizationAssistantDefaults::TEMPERATURE, $settings->temperature);
  }

  #[Test]
  public function testMergedWithLeavesOmittedFieldsUnchanged(): void
  {
    $settings = new OrganizationAssistantSettings(
      enabled: true,
      model: 'mistral:7b',
      temperature: 0.7,
      includeBusinessContext: false,
    );

    $merged = $settings->mergedWith(['temperature' => 0.1]);

    self::assertTrue($merged->enabled);
    self::assertSame('mistral:7b', $merged->model, 'An omitted model must not clear an existing override.');
    self::assertSame(0.1, $merged->temperature);
    self::assertFalse($merged->includeBusinessContext);
  }

  #[Test]
  public function testMergedWithExplicitNullModelRevertsToTheOperatorDefault(): void
  {
    $settings = new OrganizationAssistantSettings(enabled: true, model: 'mistral:7b');

    self::assertNull($settings->mergedWith(['model' => null])->model);
  }

  #[Test]
  public function testMergedWithAcceptsAnIntegerTemperature(): void
  {
    $merged = new OrganizationAssistantSettings()->mergedWith(['temperature' => 1]);

    self::assertSame(1.0, $merged->temperature);
  }
}

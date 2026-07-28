<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\Common\Collections\ArrayCollection;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionTemplateRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionTemplateRecordTest.
 *
 * @category Record Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionTemplateRecord::class)]
final class InterventionTemplateRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorInitializesThePlannedItemsCollection(): void
  {
    $record = new InterventionTemplateRecord();

    self::assertInstanceOf(ArrayCollection::class, $record->items);
    self::assertCount(0, $record->items);
  }

  #[Test]
  public function testANewRecordDefaultsToASiteSetupTemplateOfNormalPriority(): void
  {
    $record = new InterventionTemplateRecord();

    self::assertSame('site_setup', $record->type);
    self::assertSame('normal', $record->priority);
    self::assertSame([], $record->labelIds);
    self::assertNull($record->organization);
    self::assertNull($record->description);
    self::assertNull($record->defaultSiteId);
    self::assertNull($record->defaultResponsibleId);
    self::assertNull($record->duration);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\Common\Collections\ArrayCollection;
use Organization\Infrastructure\Persistence\Doctrine\Record\TeamRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TeamRecordTest.
 *
 * @category Record Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TeamRecord::class)]
final class TeamRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorInitializesTheMembersCollection(): void
  {
    $record = new TeamRecord();

    self::assertInstanceOf(ArrayCollection::class, $record->members);
    self::assertCount(0, $record->members);
  }

  #[Test]
  public function testANewRecordHasNoOrganizationAndAnEmptyDescription(): void
  {
    $record = new TeamRecord();

    self::assertNull($record->organization);
    self::assertSame('', $record->description);
  }
  // #endregion
}

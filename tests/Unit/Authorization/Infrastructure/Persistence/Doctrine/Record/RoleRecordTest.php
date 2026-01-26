<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Record;

use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleRecord;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RoleRecordTest.
 *
 * @category Record Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleRecord::class)]
final class RoleRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorInitializesPermissionsCollection(): void
  {
    $record = new RoleRecord();

    self::assertInstanceOf(ArrayCollection::class, $record->permissions);
    self::assertCount(0, $record->permissions);
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Test UserRecordTest.
 *
 * @category Record Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserRecord::class)]
final class UserRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorInitializesRolesCollection(): void
  {
    $record = new UserRecord();

    self::assertInstanceOf(ArrayCollection::class, $record->roles);
    self::assertCount(0, $record->roles);
  }
  // #endregion
}

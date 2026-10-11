<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Persistence\Doctrine\Record;

use Authorization\Infrastructure\Persistence\Doctrine\Record\UserRoleRecord;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Test UserRecordTest.
 *
 * @category Record Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserRecord::class)]
#[CoversClass(UserRoleRecord::class)]
final class UserRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function userPersistenceLeavesRoleMembershipToAuthorization(): void
  {
    self::assertFalse(new ReflectionClass(UserRecord::class)->hasProperty('roles'));
    $mapping = new ReflectionClass(UserRoleRecord::class);
    self::assertSame('user_roles', $mapping->getAttributes(ORM\Table::class)[0]->newInstance()->name);
    foreach (['userId' => 'user_id', 'roleId' => 'role_id'] as $property => $column) {
      $field = $mapping->getProperty($property);
      self::assertCount(1, $field->getAttributes(ORM\Id::class));
      self::assertSame($column, $field->getAttributes(ORM\Column::class)[0]->newInstance()->name);
      self::assertSame('string', $field->getAttributes(ORM\Column::class)[0]->newInstance()->type);
    }
  }
  // #endregion
}

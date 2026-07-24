<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\{Inspector, InspectorType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

/**
 * Test Inspector.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Inspector::class)]
final class InspectorTest extends TestCase
{
  #[Test]
  public function itBuildsAUserInspectorAndTrimsTheName(): void
  {
    $inspector = Inspector::forUser('user-42', '  Alice  ');

    self::assertSame(InspectorType::USER, $inspector->type);
    self::assertSame('Alice', $inspector->name);
    self::assertSame('user-42', $inspector->userId);
    self::assertNull($inspector->organizationName);
  }

  #[Test]
  public function itRejectsAnEmptyUserName(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forUser('user-42', '   ');
  }

  #[Test]
  public function itRejectsAnOverlongUserName(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forUser('user-42', str_repeat('a', 256));
  }

  #[Test]
  public function itRejectsAnEmptyUserId(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forUser('   ', 'Alice');
  }

  #[Test]
  public function itBuildsAnExternalInspectorWithAnOrganization(): void
  {
    $inspector = Inspector::forExternal('  Bob  ', '  Acme  ');

    self::assertSame(InspectorType::EXTERNAL, $inspector->type);
    self::assertSame('Bob', $inspector->name);
    self::assertNull($inspector->userId);
    self::assertSame('Acme', $inspector->organizationName);
  }

  #[Test]
  public function itNormalizesAnEmptyExternalOrganizationToNull(): void
  {
    $inspector = Inspector::forExternal('Bob', '   ');

    self::assertNull($inspector->organizationName);
  }

  #[Test]
  public function itRejectsAnEmptyExternalName(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forExternal('   ');
  }

  #[Test]
  public function itRejectsAnOverlongExternalOrganizationName(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forExternal('Bob', str_repeat('o', 256));
  }

  #[Test]
  public function itRejectsAnOverlongExternalName(): void
  {
    $this->expectException(InvalidValueException::class);

    Inspector::forExternal(str_repeat('b', 256));
  }

  #[Test]
  public function itBuildsAnExternalInspectorWithoutAnOrganization(): void
  {
    $inspector = Inspector::forExternal('  Dave  ');

    self::assertSame(InspectorType::EXTERNAL, $inspector->type);
    self::assertSame('Dave', $inspector->name);
    self::assertNull($inspector->userId);
    self::assertNull($inspector->organizationName);
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $inspector = Inspector::reconstitute(
      type: InspectorType::EXTERNAL,
      name: 'Carol',
      userId: null,
      organizationName: 'Globex',
    );

    self::assertSame(InspectorType::EXTERNAL, $inspector->type);
    self::assertSame('Carol', $inspector->name);
    self::assertNull($inspector->userId);
    self::assertSame('Globex', $inspector->organizationName);
  }
}

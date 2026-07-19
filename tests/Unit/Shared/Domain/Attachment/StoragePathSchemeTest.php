<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Attachment\StoragePathScheme;

#[CoversClass(StoragePathScheme::class)]
final class StoragePathSchemeTest extends TestCase
{
  #[Test]
  public function testBuildProducesTheDeterministicScheme(): void
  {
    $path = StoragePathScheme::build('facility', 'facility-id', 'attachment-id', 'report.pdf');

    self::assertSame('facility/facility-id/attachments/attachment-id_report.pdf', $path);
  }

  #[Test]
  public function testBuildStripsPathTraversalFromFileName(): void
  {
    $path = StoragePathScheme::build('inspection', 'insp-id', 'att-id', '../../etc/evil.pdf');

    self::assertSame('inspection/insp-id/attachments/att-id_evil.pdf', $path);
  }

  #[Test]
  public function testBuildStripsWindowsStyleDirectorySeparators(): void
  {
    $path = StoragePathScheme::build('intervention', 'int-id', 'att-id', 'C:\\Windows\\evil.pdf');

    self::assertSame('intervention/int-id/attachments/att-id_evil.pdf', $path);
  }
}

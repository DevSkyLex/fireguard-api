<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Class ExportIdentityUnicodeTest
 * Domain limits agree with transport and UI character lengths.
 *
 * @category Test
 */
final class ExportIdentityUnicodeTest extends TestCase
{
  #[Test]
  public function twoHundredAccentedCharactersRemainValid(): void
  {
    $reference = str_repeat('é', 200);
    self::assertSame($reference, ExportIdentity::text($reference, 200));
  }

  #[Test]
  public function twoHundredAndOneCharactersExceedTheReferenceLimit(): void
  {
    $this->expectException(MaintenanceExportException::class);
    ExportIdentity::text(str_repeat('é', 201), 200);
  }
}

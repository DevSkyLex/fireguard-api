<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\CreateImportJob;

use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CreateImportJobCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateImportJobCommand::class)]
final class CreateImportJobCommandTest extends TestCase
{
  #[Test]
  public function itExposesAllConstructorValues(): void
  {
    $command = new CreateImportJobCommand(
      userId: 'user-1',
      organizationId: 'org-1',
      kind: 'equipment',
      fileName: 'equipment.csv',
      contents: "type\nfire_extinguisher\n",
      mimeType: 'text/csv',
      size: 25,
    );

    self::assertSame('user-1', $command->userId);
    self::assertSame('org-1', $command->organizationId);
    self::assertSame('equipment', $command->kind);
    self::assertSame('equipment.csv', $command->fileName);
    self::assertSame("type\nfire_extinguisher\n", $command->contents);
    self::assertSame('text/csv', $command->mimeType);
    self::assertSame(25, $command->size);
  }
}

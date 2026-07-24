<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\ProcessImportJob;

use Import\Application\UseCase\Command\ProcessImportJob\ProcessImportJobCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ProcessImportJobCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProcessImportJobCommand::class)]
final class ProcessImportJobCommandTest extends TestCase
{
  #[Test]
  public function itExposesTheImportJobId(): void
  {
    $command = new ProcessImportJobCommand('job-1');

    self::assertSame('job-1', $command->importJobId);
  }
}

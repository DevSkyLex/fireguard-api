<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling;

use DOMDocument;
use DOMElement;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\{CoversNothing, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function dirname;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * Test CheckCoverageTest.
 *
 * @category Tooling Tests
 *
 * @version 1.0.0
 *
 * @author FireGuard
 */
#[CoversNothing]
final class CheckCoverageTest extends TestCase
{
  // #region Methods
  /**
   * Enforce the unrounded line threshold, including its exact boundary.
   *
   * @since 1.0.0
   *
   * @param int $covered the synthetic covered-line count
   * @param int $expectedExit the expected checker status
   */
  #[Test]
  #[DataProvider('coverageProvider')]
  public function testEnforcesLineThreshold(int $covered, int $expectedExit): void
  {
    $result = $this->runChecker($this->completeReport($covered));

    self::assertSame($expectedExit, $result->getExitCode(), $result->getErrorOutput());
    self::assertStringContainsString($covered . '/10000', $result->getOutput());
  }

  /**
   * Reject absent input before attempting XML parsing.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsMissingReport(): void
  {
    $result = new Process([PHP_BINARY, 'bin/check-coverage.php', 'var/coverage/report-that-does-not-exist.xml', '90'], dirname(__DIR__, 3));
    $result->run(null, ['XDEBUG_MODE' => 'off']);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('missing or unreadable', $result->getErrorOutput());
  }

  /**
   * Reject malformed, external-entity, empty and source-incomplete reports.
   *
   * @since 1.0.0
   *
   * @param string $report the invalid XML report
   */
  #[Test]
  #[DataProvider('invalidReportProvider')]
  public function testRejectsInvalidReport(string $report): void
  {
    $result = $this->runChecker($report);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('Coverage check failed:', $result->getErrorOutput());
  }

  /**
   * Reject optimistic aggregate metrics that disagree with the file totals.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsInconsistentTotals(): void
  {
    $document = new DOMDocument();
    self::assertTrue($document->loadXML($this->completeReport(8999)));
    $summary = $document->getElementsByTagName('project')->item(0)?->lastChild;
    self::assertInstanceOf(DOMElement::class, $summary);
    $summary->setAttribute('coveredstatements', '10000');
    $xml = $document->saveXML();
    self::assertIsString($xml);

    self::assertSame(2, $this->runChecker($xml)->getExitCode());
  }

  /**
   * Prevent a narrowed source denominator from passing the aggregate threshold.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsMissingSourceFile(): void
  {
    $document = new DOMDocument();
    self::assertTrue($document->loadXML($this->completeReport(10000)));
    $file = $document->getElementsByTagName('file')->item(1);
    self::assertInstanceOf(DOMElement::class, $file);
    $file->parentNode?->removeChild($file);
    $xml = $document->saveXML();
    self::assertIsString($xml);

    $result = $this->runChecker($xml);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('omits application source files', $result->getErrorOutput());
  }

  /**
   * Provide coverage percentages immediately around the required threshold.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{int, int}>
   */
  public static function coverageProvider(): iterable
  {
    yield 'exactly 90 percent' => [9000, 0];
    yield 'above the threshold' => [9630, 0];
    yield '89.99 percent does not round up' => [8999, 1];
    yield 'no covered lines' => [0, 1];
  }

  /**
   * Keep invalid XML inputs hermetic and independent of external resources.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{string}>
   */
  public static function invalidReportProvider(): iterable
  {
    yield 'invalid XML' => ['<coverage>'];
    yield 'DTD rejected' => ['<!DOCTYPE coverage SYSTEM "https://example.invalid/coverage.dtd"><coverage/>'];
    yield 'missing summary' => ['<coverage><project/></coverage>'];
    yield 'empty report' => ['<coverage><project><metrics statements="0" coveredstatements="0"/></project></coverage>'];
  }

  /**
   * Build a synthetic report containing the complete source inventory.
   *
   * @since 1.0.0
   *
   * @param int $covered the covered lines assigned to the first source file
   *
   * @return string the synthetic Clover XML
   */
  private function completeReport(int $covered): string
  {
    $document = new DOMDocument();
    $coverage = $document->appendChild($document->createElement('coverage'));
    $project = $coverage->appendChild($document->createElement('project'));
    $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src', FilesystemIterator::SKIP_DOTS));
    $first = true;
    foreach ($source as $sourceFile) {
      if (!$sourceFile instanceof SplFileInfo || !$sourceFile->isFile() || 'php' !== $sourceFile->getExtension()) {
        continue;
      }

      $file = $document->createElement('file');
      $file->setAttribute('name', $sourceFile->getPathname());
      $metrics = $document->createElement('metrics');
      $metrics->setAttribute('statements', $first ? '10000' : '0');
      $metrics->setAttribute('coveredstatements', $first ? (string) $covered : '0');
      $file->appendChild($metrics);
      $project->appendChild($file);
      $first = false;
    }

    $metrics = $document->createElement('metrics');
    $metrics->setAttribute('statements', '10000');
    $metrics->setAttribute('coveredstatements', (string) $covered);
    $project->appendChild($metrics);
    $xml = $document->saveXML();
    self::assertIsString($xml);

    return $xml;
  }

  /**
   * Run the public CLI against a temporary report without using a shell.
   *
   * @since 1.0.0
   *
   * @param string $report the synthetic Clover XML
   *
   * @return Process the completed checker process
   */
  private function runChecker(string $report): Process
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-coverage-');
    self::assertIsString($path);

    try {
      self::assertNotFalse(file_put_contents($path, $report));
      $process = new Process([PHP_BINARY, 'bin/check-coverage.php', $path, '90'], dirname(__DIR__, 3));
      $process->run(null, ['XDEBUG_MODE' => 'off']);

      return $process;
    } finally {
      unlink($path);
    }
  }
  // #endregion
}

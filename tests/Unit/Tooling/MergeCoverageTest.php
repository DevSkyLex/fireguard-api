<?php

declare(strict_types=1);

namespace Tests\Unit\Tooling;

use DOMDocument;
use DOMXPath;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SebastianBergmann\CodeCoverage\{CodeCoverage, Filter};
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SplFileInfo;
use Symfony\Component\Process\Process;

use function dirname;
use function file_put_contents;
use function is_file;
use function realpath;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_BINARY;

/**
 * Test MergeCoverageTest.
 *
 * @category Tooling Tests
 *
 * @version 1.0.0
 *
 * @author FireGuard
 */
#[CoversNothing]
final class MergeCoverageTest extends TestCase
{
  /**
   * @var list<string>
   */
  private array $temporaryFiles = [];

  /**
   * Remove only the temporary reports created by this test.
   *
   * @since 1.0.0
   */
  protected function tearDown(): void
  {
    foreach ($this->temporaryFiles as $file) {
      if (is_file($file)) {
        unlink($file);
      }
    }
    parent::tearDown();
  }

  /**
   * Overlapping execution across three jobs covers each source line once.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testUnionsLinesAndPreservesUncoveredSources(): void
  {
    $output = $this->temporaryFile();
    $unit = $this->report([145 => ['unit'], 155 => [], 165 => []]);
    $integration = $this->report([145 => ['integration'], 155 => ['integration'], 165 => []]);
    $e2e = $this->report([145 => ['e2e'], 155 => [], 165 => []]);
    $result = $this->merge($output, [$unit, $integration, $e2e]);

    self::assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    $document = new DOMDocument();
    self::assertTrue($document->load($output));
    $xpath = new DOMXPath($document);
    $file = '//file[@name="' . $this->sampleSource() . '"]';
    self::assertSame('3', $xpath->evaluate('string(' . $file . '/metrics/@statements)'));
    self::assertSame('2', $xpath->evaluate('string(' . $file . '/metrics/@coveredstatements)'));
    self::assertSame('3', $xpath->evaluate('string(' . $file . '/line[@num="145"]/@count)'));
    self::assertSame('1', $xpath->evaluate('string(' . $file . '/line[@num="155"]/@count)'));
    self::assertSame('0', $xpath->evaluate('string(' . $file . '/line[@num="165"]/@count)'));

    $check = new Process([PHP_BINARY, 'bin/check-coverage.php', $output, '90'], dirname(__DIR__, 3));
    $check->run(null, ['XDEBUG_MODE' => 'off']);
    self::assertSame(1, $check->getExitCode(), $check->getErrorOutput());
    self::assertStringContainsString('below the required threshold', $check->getErrorOutput());
  }

  /**
   * A missing job cannot be treated as a zero-sized successful contribution.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsMissingReport(): void
  {
    $result = $this->merge($this->temporaryFile(), ['missing-unit.php', 'missing-integration.php', 'missing-e2e.php']);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('All three coverage reports must exist', $result->getErrorOutput());
  }

  /**
   * Repeating one artifact must not stand in for a different test job.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsDuplicateReport(): void
  {
    $input = $this->temporaryFile();
    $result = $this->merge($this->temporaryFile(), [$input, $input, $input]);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('distinct coverage report', $result->getErrorOutput());
  }

  /**
   * Invalid exports must fail before any complete coverage report is produced.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsInvalidCoverageObject(): void
  {
    $input = $this->temporaryFile();
    self::assertNotFalse(file_put_contents($input, '<?php return "not coverage";'));
    $result = $this->merge($this->temporaryFile(), [$input, $this->temporaryFile(), $this->temporaryFile()]);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('not a PHPUnit coverage object', $result->getErrorOutput());
  }

  /**
   * Every suite must measure the full application with compatible source paths.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testRejectsNarrowedSourceFilter(): void
  {
    $input = $this->report([145 => ['unit']], false);
    $result = $this->merge($this->temporaryFile(), [$input, $this->temporaryFile(), $this->temporaryFile()]);

    self::assertSame(2, $result->getExitCode());
    self::assertStringContainsString('all application sources at the current checkout path', $result->getErrorOutput());
  }

  /**
   * Export genuine PHPUnit coverage objects without executing application code.
   *
   * @since 1.0.0
   *
   * @param array<int, list<string>> $lines synthetic line execution identifiers
   * @param bool $complete whether to include the complete application source filter
   *
   * @return string the native report path
   */
  private function report(array $lines, bool $complete = true): string
  {
    $filter = new Filter();
    if ($complete) {
      $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src', FilesystemIterator::SKIP_DOTS));
      foreach ($source as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
          $filter->includeFile($file->getPathname());
        }
      }
    } else {
      $filter->includeFile($this->sampleSource());
    }
    $coverage = new CodeCoverage($this->createStub(Driver::class), $filter);
    $coverage->disableAnnotationsForIgnoringCode();
    $coverage->getData(true)->setLineCoverage([$this->sampleSource() => $lines]);
    $path = $this->temporaryFile();
    new PHP()->process($coverage, $path);

    return $path;
  }

  /**
   * Use three stable executable denial lines from an existing source file.
   *
   * @since 1.0.0
   *
   * @return string the canonical source filename
   */
  private function sampleSource(): string
  {
    $path = realpath(dirname(__DIR__, 3) . '/src/Auth/Infrastructure/Security/DPoP/DPoPValidator.php');
    self::assertIsString($path);

    return $path;
  }

  /**
   * Allocate a unique artifact and register it for cleanup.
   *
   * @since 1.0.0
   *
   * @return non-empty-string the temporary report filename
   */
  private function temporaryFile(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-coverage-merge-');
    self::assertIsString($path);
    self::assertNotSame('', $path);
    $this->temporaryFiles[] = $path;

    return $path;
  }

  /**
   * Run the same merge command as CI without shell expansion or a coverage driver.
   *
   * @since 1.0.0
   *
   * @param string $output the destination Clover filename
   * @param list<string> $reports the three native suite reports
   *
   * @return Process the completed merger process
   */
  private function merge(string $output, array $reports): Process
  {
    $process = new Process([PHP_BINARY, '-d', 'memory_limit=1G', 'bin/merge-coverage.php', $output, ...$reports], dirname(__DIR__, 3));
    $process->setTimeout(120);
    $process->run(null, ['XDEBUG_MODE' => 'off']);

    return $process;
  }
}

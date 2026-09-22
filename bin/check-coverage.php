<?php

declare(strict_types=1);

/**
 * Check the complete application Clover report against its executable-line floor.
 *
 * Report paths are data, never shell commands. XML entities are not expanded and
 * documents with a DTD are rejected before parsing. Missing source files, empty
 * metrics and inconsistent totals fail closed instead of yielding a false pass.
 *
 * @category Quality
 *
 * @version 1.0.0
 *
 * @author FireGuard
 */
try {
  if (3 !== $argc || !is_numeric($argv[2])) {
    throw new RuntimeException('Usage: php bin/check-coverage.php <clover.xml> <minimum-percent>');
  }

  $minimum = (float) $argv[2];
  if (!is_finite($minimum) || $minimum < 0 || $minimum > 100) {
    throw new RuntimeException('The minimum percentage must be between 0 and 100.');
  }

  if (!is_file($argv[1]) || !is_readable($argv[1])) {
    throw new RuntimeException('The Clover report is missing or unreadable.');
  }

  $contents = file_get_contents($argv[1]);
  if (false === $contents || false !== stripos($contents, '<!DOCTYPE')) {
    throw new RuntimeException('The Clover report must be XML without a document type.');
  }

  libxml_use_internal_errors(true);
  $document = new DOMDocument();
  if (!$document->loadXML($contents, LIBXML_NONET)) {
    throw new RuntimeException('The Clover report is invalid XML.');
  }

  $xpath = new DOMXPath($document);
  $projectMetrics = $xpath->query('/coverage/project/metrics');
  if (false === $projectMetrics || 1 !== $projectMetrics->length) {
    throw new RuntimeException('The Clover report must contain exactly one project summary.');
  }

  $files = $xpath->query('/coverage/project//file');
  if (false === $files || 0 === $files->length) {
    throw new RuntimeException('The Clover report contains no source files.');
  }

  $reportedFiles = [];
  $total = 0;
  $covered = 0;
  foreach ($files as $file) {
    if (!$file instanceof DOMElement) {
      throw new RuntimeException('The Clover report contains an invalid file.');
    }

    $name = realpath($file->getAttribute('name'));
    if (false === $name || isset($reportedFiles[$name])) {
      throw new RuntimeException('The Clover report contains an unknown or duplicate source file.');
    }

    $metrics = $xpath->query('metrics', $file);
    if (false === $metrics || 1 !== $metrics->length) {
      throw new RuntimeException('A Clover source file has no unique metrics.');
    }

    $metric = $metrics->item(0);
    if (!$metric instanceof DOMElement) {
      throw new RuntimeException('A Clover source file has invalid metrics.');
    }

    $statements = $metric->getAttribute('statements');
    $coveredStatements = $metric->getAttribute('coveredstatements');
    if (!ctype_digit($statements) || !ctype_digit($coveredStatements) || (int) $coveredStatements > (int) $statements) {
      throw new RuntimeException('A Clover source file has invalid line metrics.');
    }

    $reportedFiles[$name] = true;
    $total += (int) $statements;
    $covered += (int) $coveredStatements;
  }

  $sourceFiles = 0;
  $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS));
  foreach ($source as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
      continue;
    }

    ++$sourceFiles;
    if (!isset($reportedFiles[$file->getRealPath()])) {
      throw new RuntimeException('The Clover report omits application source files. Run make coverage-check.');
    }
  }

  $summary = $projectMetrics->item(0);
  if (!$summary instanceof DOMElement || 0 === $total || $sourceFiles !== count($reportedFiles)
    || (string) $total !== $summary->getAttribute('statements')
    || (string) $covered !== $summary->getAttribute('coveredstatements')) {
    throw new RuntimeException('The Clover report has empty, incomplete or inconsistent project metrics.');
  }

  $percentage = 100 * $covered / $total;
  printf("Executable lines: %.4f%% (%d/%d), required: %.2f%%, source files: %d.\n", $percentage, $covered, $total, $minimum, $sourceFiles);
  if ($percentage < $minimum) {
    fwrite(STDERR, "Application line coverage is below the required threshold.\n");

    exit(1);
  }
} catch (Throwable $exception) {
  fwrite(STDERR, 'Coverage check failed: ' . $exception->getMessage() . "\n");

  exit(2);
}

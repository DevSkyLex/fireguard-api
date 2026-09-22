<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\{CodeCoverage, Filter};
use SebastianBergmann\CodeCoverage\Report\Clover;

/**
 * Merge the three trusted PHPUnit artifacts produced by this CI run.
 *
 * PHPUnit PHP reports contain serialized coverage objects and executable PHP.
 * Only pass artifacts from the current test jobs, never external downloads.
 * Native merging unions execution data, counting a line once across suites.
 *
 * @category Quality
 *
 * @version 1.0.0
 *
 * @author FireGuard
 */
try {
  require dirname(__DIR__) . '/vendor/autoload.php';

  if (5 !== $argc || '' === $argv[1]) {
    throw new RuntimeException('Usage: php bin/merge-coverage.php <clover.xml> <unit.php> <integration.php> <e2e.php>');
  }

  $paths = [];
  foreach (array_slice($argv, 2) as $input) {
    $path = realpath($input);
    if (false === $path || !is_file($path) || !is_readable($path)) {
      throw new RuntimeException('All three coverage reports must exist and be readable.');
    }
    if (isset($paths[$path])) {
      throw new RuntimeException('Each test job must provide a distinct coverage report.');
    }
    $paths[$path] = true;
  }

  $filter = new Filter();
  $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS));
  foreach ($source as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
      $filter->includeFile($file->getPathname());
    }
  }
  $expectedFiles = $filter->files();
  sort($expectedFiles);

  $merged = null;
  foreach (array_keys($paths) as $path) {
    $part = require $path;
    if (!$part instanceof CodeCoverage) {
      throw new RuntimeException('A report is not a PHPUnit coverage object.');
    }

    $files = $part->filter()->files();
    sort($files);
    if ($files !== $expectedFiles) {
      throw new RuntimeException('Every report must cover all application sources at the current checkout path.');
    }

    if (null === $merged) {
      $merged = $part;
    } else {
      $merged->merge($part);
    }
  }

  if (!$merged instanceof CodeCoverage) {
    throw new RuntimeException('No coverage data was merged.');
  }
  $merged->includeUncoveredFiles();
  $merged->disableAnnotationsForIgnoringCode();
  new Clover()->process($merged, $argv[1]);
  fwrite(STDOUT, "Merged all three test-job reports over the complete application source inventory.\n");
} catch (Throwable $exception) {
  fwrite(STDERR, 'Coverage merge failed: ' . $exception->getMessage() . "\n");

  exit(2);
}

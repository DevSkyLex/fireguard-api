<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\{DeptracConfig, Layer, Ruleset};

/**
 * Module ownership gate, complementary to the four-layer rules in deptrac.yaml.
 * The baseline enumerates exact class pairs; no whole module is exempt.
 */
return static function (DeptracConfig $config): void {
  $config->paths(__DIR__ . '/src')->cacheFile(__DIR__ . '/var/deptrac-modules.cache');
  $public = [];
  $private = [];
  foreach (glob(__DIR__ . '/src/*/MODULE.md') ?: [] as $document) {
    $module = basename(dirname($document));
    $surface = 'Application\\(?:Port|Contract)\\';
    if ('Shared' === $module) {
      $surface = '(?:Application\\(?:Port|Contract|Message)\\|Domain\\)';
    }
    $prefix = '^' . preg_quote($module, '/') . '\\';
    $public[$module] = Layer::withName($module . 'Public')->collectors(ClassLikeConfig::create($prefix . $surface));
    $private[$module] = Layer::withName($module . 'Private')->collectors(ClassLikeConfig::create($prefix . '(?!' . $surface . ').*'));
    $config->layers($public[$module], $private[$module]);
  }
  foreach ($private as $module => $layer) {
    $config->rulesets(
      Ruleset::forLayer($layer)->accesses(...array_values($public)),
      Ruleset::forLayer($public[$module])->accesses($layer, ...array_values($public)),
    );
  }
  $baseline = __DIR__ . '/deptrac.modules.baseline.yaml';
  if (is_file($baseline)) {
    $config->baseline($baseline);
  }
};

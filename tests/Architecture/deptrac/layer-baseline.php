<?php

declare(strict_types=1);

use App\Tests\Architecture\Support\ExactDependencyBaseline;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Yaml\Yaml;

/**
 * Reuses exact module exceptions without declaring legal layer edges as skipped violations.
 * The module gate retains the full baseline and rejects every stale pair.
 */
return static function (ContainerConfigurator $container): void {
  /** @var array{deptrac: array{ruleset: array<string, list<string>|null>}} $definition */
  $definition = Yaml::parseFile(dirname(__DIR__, 3) . '/deptrac.yaml');
  $container->extension('deptrac', [
    'skip_violations' => ExactDependencyBaseline::load()->forLayerRuleset($definition['deptrac']['ruleset']),
  ]);
};

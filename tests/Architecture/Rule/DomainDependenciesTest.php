<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use App\Tests\Architecture\Support\{ArchitectureLayer, DependencyRules, ExactDependencyBaseline};
use PHPat\Test\Builder\Rule;

/**
 * Enforces layer direction and owner-published contracts without blanket legacy exemptions.
 *
 * @category Architecture Rule Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DomainDependenciesTest
{
  /**
   * @return iterable<Rule> Rules for the actual module namespaces
   */
  public function testLayerDependencies(): iterable
  {
    yield from new DependencyRules(ExactDependencyBaseline::load())->forLayer(ArchitectureLayer::DOMAIN);
  }
}

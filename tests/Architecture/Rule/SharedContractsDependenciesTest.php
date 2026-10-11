<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use App\Tests\Architecture\Support\{DependencyRules, ExactDependencyBaseline};
use PHPat\Test\Builder\Rule;

/**
 * Prevents public contracts and Shared ports from exporting private or framework types.
 *
 * @category Architecture Rule Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SharedContractsDependenciesTest
{
  /**
   * @return iterable<Rule> Framework-independent contract rules
   */
  public function testPublicContractsArePure(): iterable
  {
    yield from new DependencyRules(ExactDependencyBaseline::load())->forContracts();
  }
}

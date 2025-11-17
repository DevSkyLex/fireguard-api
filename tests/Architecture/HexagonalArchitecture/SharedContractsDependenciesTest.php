<?php

declare(strict_types=1);

namespace App\Tests\Architecture\HexagonalArchitecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Test SharedContractsDependenciesTest
 *
 * Ensures shared application ports depend
 * only on Domain contracts
 *
 * @category Architecture Tests
 * @package App\Tests\Architecture\HexagonalArchitecture
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SharedContractsDependenciesTest extends BaseHexagonalArchitectureTest
{
  //#region Constants
  /**
   * Constant PORTS_DESCRIPTION
   *
   * Description for shared ports
   *
   * @access public
   *
   * @var string PORTS_DESCRIPTION
   */
  public const string PORTS_DESCRIPTION = 'Shared ports must expose only Domain contracts.';
  //#endregion

  //#region Methods
  /**
   * Method testSharedPortsDependOnlyOnDomains
   *
   * Ensures shared ports only depend
   * on Domain namespaces
   *
   * @access public
   *
   * @return iterable<Rule> Iterable of rules for shared ports
   */
  public function testSharedPortsDependOnlyOnDomains(): iterable
  {
    $portSelectors = $this->sharedApplicationPortSelectors();

    if ($portSelectors === []) {
      return [];
    }

    $domainSelectors = [];

    foreach ($this->modulesHavingLayer(layer: self::DOMAIN_LAYER) as $module) {
      $domainSelectors = [
        ...$domainSelectors,
        ...$this->selectorsForModuleLayer(module: $module, layer: self::DOMAIN_LAYER),
      ];
    }

    if ($domainSelectors === []) {
      return [];
    }

    yield PHPat::rule()
      ->classes(...$portSelectors)
      ->canOnlyDependOn()
      ->classes(...$domainSelectors)
      ->because(tips: self::PORTS_DESCRIPTION);
  }
  //#endregion
}

<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use App\Tests\Architecture\Support\ArchitectureLayer;
use App\Tests\Architecture\Support\ArchitectureNamespace;
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Test SharedContractsDependenciesTest
 * @final
 *
 * Ensures shared application ports depend
 * only on Domain contracts
 *
 * @category Architecture Rule Tests
 * @package App\Tests\Architecture\Rule
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

    if ($portSelectors === []) return [];

    /** @var list<SelectorInterface> $allowedSelectors */
    $allowedSelectors = $this->sharedApplicationSupportSelectors();

    foreach ($this->modulesHavingLayer(layer: ArchitectureLayer::DOMAIN) as $module) {
      $allowedSelectors = [
        ...$allowedSelectors,
        ...$this->selectorsForModuleLayer(
          module: $module,
          layer: ArchitectureLayer::DOMAIN
        ),
      ];
    }

    yield PHPat::rule()
      ->classes(...$portSelectors)
      ->canOnlyDependOn()
      ->classes(...$allowedSelectors)
      ->because(tips: self::PORTS_DESCRIPTION);
  }

  /**
   * Method testSharedPortsAreInfrastructureAgnostic
   *
   * Ensures shared ports do not couple to
   * infrastructure/framework namespaces
   *
   * @access public
   *
   * @return Rule The test rule
   */
  public function testSharedPortsAreInfrastructureAgnostic(): Rule
  {
    $portSelectors = $this->sharedApplicationPortSelectors();

    if ($portSelectors === []) $portSelectors = [
      Selector::inNamespace(
        namespace: sprintf(
          '%s\\%s\\%s',
          ArchitectureNamespace::SHARED->value,
          ArchitectureLayer::APPLICATION->value,
          'Port'
        )
      ),
    ];

    $forbiddenSelectors = [
      ...$this->selectorsForLayer(layer: ArchitectureLayer::INFRASTRUCTURE),
      Selector::inNamespace(namespace: sprintf(
        '%s\\%s',
        ArchitectureNamespace::SHARED->value,
        ArchitectureLayer::INFRASTRUCTURE->value
      )),
      Selector::inNamespace(namespace: 'Symfony'),
    ];

    return PHPat::rule()
      ->classes(...$portSelectors)
      ->shouldNotDependOn()
      ->classes(...$forbiddenSelectors)
      ->because(tips: 'Shared ports must remain framework-agnostic.');
  }

  /**
   * Method sharedApplicationSupportSelectors
   *
   * Allows shared ports to depend on shared
   * support namespaces (messages, logs...)
   *
   * @access private
   *
   * @return list<SelectorInterface> List of allowed shared application support namespaces
   */
  private function sharedApplicationSupportSelectors(): array
  {
    $namespaces = [
      sprintf('%s\\Application\\Message', ArchitectureNamespace::SHARED->value),
      sprintf('%s\\Application\\Log', ArchitectureNamespace::SHARED->value),
    ];

    /**
     * Selectors for shared application
     * support namespaces
     *
     * @var list<SelectorInterface>
     */
    return $this->selectorsForNamespaces(namespaces: $namespaces);
  }
  //#endregion
}


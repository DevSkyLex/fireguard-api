<?php

declare(strict_types=1);

namespace App\Tests\Architecture\HexagonalArchitecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Test ApplicationDependenciesTest
 * @final
 *
 * Test class for Application layer dependencies
 *
 * @category Architecture Tests
 * @package App\Tests\Architecture\HexagonalArchitecture
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApplicationDependenciesTest extends BaseHexagonalArchitectureTest
{
  //#region Constants
  /**
   * Constant LAYER
   *
   * Layer to test dependencies
   *
   * @access public
   *
   * @var string LAYER
   */
  public const string LAYER = self::APPLICATION_LAYER;

  /**
   * Constant FORBIDDEN_LAYER
   *
   * Layer that should not be used by
   * the Application layer
   *
   * @access public
   *
   * @var string FORBIDDEN_LAYER
   */
  public const string FORBIDDEN_LAYER = self::INFRASTRUCTURE_LAYER;
  //#endregion

  //#region Methods
  /**
   * Method testApplicationDoesNotDependOnInfrastructure
   *
   * Test Application layer does not depend
   * on Infrastructure layer
   *
   * @access public
   *
   * @return Rule Returns the test rule
   */
  public function testApplicationDoesNotDependOnInfrastructure(): Rule
  {
    $applicationSelectors = $this->selectorsForLayer(layer: self::LAYER);
    $forbiddenSelectors = $this->selectorsForLayer(layer: self::FORBIDDEN_LAYER);
    $forbiddenSelectors[] = Selector::inNamespace(namespace: self::ROOT_NAMESPACE);

    return PHPat::rule()
      ->classes(...$applicationSelectors)
      ->shouldNotDependOn()
      ->classes(...$forbiddenSelectors)
      ->because(tips: 'Application layer should rely only on Domain and ports.');
  }

  /**
   * Method testApplicationDependsOnlyOnDomainAndSharedContracts
   *
   * Ensures Application layer collaborates only with its Domain and shared ports
   *
   * @access public
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testApplicationDependsOnlyOnDomainAndSharedContracts(): iterable
  {
    $sharedDomainSelectors = $this->sharedLayerSelectors(layer: self::DOMAIN_LAYER);
    $sharedPortSelectors = $this->sharedApplicationPortSelectors();

    foreach ($this->modulesHavingLayer(self::LAYER) as $module) {
      $applicationSelectors = $this->selectorsForModuleLayer(module: $module, layer: self::LAYER);

      if ($applicationSelectors === []) {
        continue;
      }

      $allowedSelectors = [
        ...$applicationSelectors,
        ...$this->selectorsForModuleLayer(module: $module, layer: 'Domain'),
        ...$sharedDomainSelectors,
        ...$sharedPortSelectors,
      ];

      yield PHPat::rule()
        ->classes(...$applicationSelectors)
        ->canOnlyDependOn()
        ->classes(...$allowedSelectors)
        ->because(tips: 'Application layer must orchestrate its Domain or shared contracts only.');
    }
  }
  //#endregion
}

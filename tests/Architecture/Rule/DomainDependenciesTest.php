<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Test DomainDependenciesTest
 * @final
 *
 * Tests that the Domain layer has no
 * outer dependencies
 *
 * @category Architecture Rule Tests
 * @package App\Tests\Architecture\Rule
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DomainDependenciesTest extends BaseHexagonalArchitectureTest
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
  public const string LAYER = self::DOMAIN_LAYER;

  /**
   * Constant FORBIDDEN_LAYERS
   *
   * Layers that should not be used by
   * the Domain layer
   *
   * @access public
   *
   * @var array<string> FORBIDDEN_LAYERS
   */
  public const array FORBIDDEN_LAYERS = [
    self::APPLICATION_LAYER,
    self::INFRASTRUCTURE_LAYER,
  ];
  //#endregion

  //#region Methods
  /**
   * Method testDomainHasNoOuterDependencies
   *
   * Tests that the Domain layer has
   * no outer dependencies
   *
   * @access public
   *
   * @return Rule The test rule
   */
  public function testDomainHasNoOuterDependencies(): Rule
  {
    $domainSelectors = $this->selectorsForLayer(layer: self::LAYER);

    $forbiddenSelectors = $this->selectorsForLayers(layers: self::FORBIDDEN_LAYERS);

    $forbiddenSelectors[] = Selector::inNamespace(namespace: self::ROOT_NAMESPACE);

    return PHPat::rule()
      ->classes(...$domainSelectors)
      ->shouldNotDependOn()
      ->classes(...$forbiddenSelectors)
      ->because(tips: 'Domain layer must remain pure and framework-agnostic.');
  }

  /**
   * Method testDomainOnlyDependsOnDomainAndSharedGlobally
   *
   * Ensures the whole Domain layer only
   * references domain contracts
   *
   * @access public
   *
   * @return Rule The test rule
   */
  public function testDomainOnlyDependsOnDomainAndSharedGlobally(): Rule
  {
    $domainSelectors = $this->selectorsForLayer(layer: self::LAYER);
    $sharedSelectors = $this->sharedLayerSelectors(layer: self::LAYER);

    $allowedSelectors = [
      ...$domainSelectors,
      ...$sharedSelectors
    ];

    return PHPat::rule()
      ->classes(...$domainSelectors)
      ->canOnlyDependOn()
      ->classes(...$allowedSelectors)
      ->because(tips: 'Domain must collaborate only through domain contracts.');
  }

  /**
   * Method testDomainDependsOnlyOnDomainAndShared
   *
   * Ensures each module Domain only depends
   * on itself and the Shared kernel
   *
   * @access public
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testDomainDependsOnlyOnDomainAndShared(): iterable
  {
    $sharedSelectors = $this->sharedLayerSelectors(self::LAYER);

    foreach ($this->modulesHavingLayer(self::LAYER) as $module) {
      $moduleSelectors = $this->selectorsForModuleLayer(
        module: $module,
        layer: self::LAYER
      );

      if ($moduleSelectors === []) {
        continue;
      }

      $allowedSelectors = [
        ...$moduleSelectors,
        ...$sharedSelectors
      ];

      yield PHPat::rule()
        ->classes(...$moduleSelectors)
        ->canOnlyDependOn()
        ->classes(...$allowedSelectors)
        ->because(tips: 'Domain layer should collaborate only within its module and the shared kernel.');
    }
  }

  /**
   * Method testDomainModulesAreIsolated
   *
   * Ensures each module domain does not depend on other module domains
   *
   * @access public
   *
   * @return iterable<Rule> Iterable of rules, one per module
   */
  public function testDomainModulesAreIsolated(): iterable
  {
    $domainModules = $this->modulesHavingLayer(self::LAYER);

    foreach ($domainModules as $module) {
      $moduleSelectors = $this->selectorsForModuleLayer($module, self::LAYER);

      if ($moduleSelectors === []) {
        continue;
      }

      $forbiddenNamespaces = [];

      foreach ($domainModules as $candidate) {
        if ($candidate->namespace === $module->namespace) {
          continue;
        }

        $namespace = $candidate->layerNamespace(self::LAYER);

        if ($namespace === '') {
          continue;
        }

        $forbiddenNamespaces[] = $namespace;
      }

      $forbiddenSelectors = $this->selectorsForNamespaces($forbiddenNamespaces);

      if ($forbiddenSelectors === []) {
        continue;
      }

      yield PHPat::rule()
        ->classes(...$moduleSelectors)
        ->shouldNotDependOn()
        ->classes(...$forbiddenSelectors)
        ->because(tips: 'Each bounded context domain must stay isolated from other domains.');
    }
  }

  /**
   * Method testSharedDomainIsFrameworkAgnostic
   *
   * Ensures Shared Domain does not couple to infrastructure/framework
   *
   * @access public
   *
   * @return Rule The test rule
   */
  public function testSharedDomainIsFrameworkAgnostic(): Rule
  {
    $sharedDomainSelectors = $this->sharedLayerSelectors(self::LAYER);

    $forbiddenSelectors = [
      ...$this->selectorsForLayer(layer: self::INFRASTRUCTURE_LAYER),
      Selector::inNamespace(namespace: sprintf('%s\\%s', self::SHARED_NAMESPACE, self::INFRASTRUCTURE_LAYER)),
      Selector::inNamespace(namespace: 'Symfony'),
    ];

    return PHPat::rule()
      ->classes(...($sharedDomainSelectors !== [] ? $sharedDomainSelectors : [
        Selector::inNamespace(namespace: sprintf('%s\\%s', self::SHARED_NAMESPACE, self::LAYER))
      ]))
      ->shouldNotDependOn()
      ->classes(...$forbiddenSelectors)
      ->because(tips: 'Shared domain must stay pure and framework agnostic.');
  }

  //#endregion
}

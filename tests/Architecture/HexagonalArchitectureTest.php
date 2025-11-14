<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class HexagonalArchitectureTest
{
  public function testSharedDomainHasNoOuterDependencies(): Rule
  {
    return PHPat::rule()
      ->classes(Selector::inNamespace('Shared\\Domain'))
      ->shouldNotDependOn()
      ->classes(
        Selector::inNamespace('Shared\\Application'),
        Selector::inNamespace('Shared\\Infrastructure'),
        Selector::inNamespace('App')
      )
      ->because('Domain layer must remain pure and framework-agnostic.');
  }

  public function testSharedApplicationDoesNotDependOnInfrastructure(): Rule
  {
    return PHPat::rule()
      ->classes(Selector::inNamespace('Shared\\Application'))
      ->shouldNotDependOn()
      ->classes(
        Selector::inNamespace('Shared\\Infrastructure'),
        Selector::inNamespace('App')
      )
      ->because('Application layer should rely only on Domain and ports.');
  }

  public function testSharedInfrastructureStaysInsideSharedModule(): Rule
  {
    return PHPat::rule()
      ->classes(Selector::inNamespace('Shared\\Infrastructure'))
      ->shouldNotDependOn()
      ->classes(Selector::inNamespace('App'))
      ->because('Shared infrastructure adapters must not couple to bounded contexts in App.');
  }
}

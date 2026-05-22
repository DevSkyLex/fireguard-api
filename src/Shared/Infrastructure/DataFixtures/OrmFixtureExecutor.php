<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\{Executor\ORMExecutor, FixtureInterface, Loader, Purger\ORMPurger};
use Doctrine\ORM\EntityManagerInterface;

class OrmFixtureExecutor
{
  /**
   * @param iterable<FixtureInterface> $fixtures
   */
  public function execute(EntityManagerInterface $entityManager, iterable $fixtures, bool $append = false): void
  {
    $loader = new Loader();
    foreach ($fixtures as $fixture) {
      $loader->addFixture($fixture);
    }

    $executor = new ORMExecutor($entityManager, new ORMPurger($entityManager));

    try {
      $executor->execute($loader->getFixtures(), $append);
    } finally {
      $entityManager->clear();
    }
  }
}

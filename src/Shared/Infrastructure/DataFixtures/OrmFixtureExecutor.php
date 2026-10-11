<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\{Executor\ORMExecutor, FixtureInterface, Loader, Purger\ORMPurger};
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Shared\Application\Port\Inbound\SeedStepPort;

/**
 * Class OrmFixtureExecutor
 *
 * Loads the supplied fixtures through Doctrine and clears the entity manager afterward.
 *
 * @category Fixture
 */
class OrmFixtureExecutor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param SeedReferenceRegistryAdapter $references scalar projections for the current execution
   *
   * @return void
   */
  public function __construct(private readonly SeedReferenceRegistryAdapter $references = new SeedReferenceRegistryAdapter())
  {
  }
  // #endregion

  // #region Methods
  /**
   * @param iterable<FixtureInterface> $fixtures
   */
  public function execute(EntityManagerInterface $entityManager, iterable $fixtures, bool $append = false): void
  {
    $this->references->reset();
    $steps = [];
    $legacy = new Loader();
    foreach ($fixtures as $fixture) {
      if ($fixture instanceof SeedStepPort) {
        if (isset($steps[$fixture->key()])) {
          throw new LogicException('Duplicate seed capability: ' . $fixture->key());
        }
        $steps[$fixture->key()] = $fixture;
      } else {
        $legacy->addFixture($fixture);
      }
    }
    $ordered = $legacy->getFixtures();
    $completed = [];
    while ([] !== $steps) {
      $advanced = false;
      foreach ($steps as $key => $step) {
        foreach ($step->dependencies() as $dependency) {
          if (!isset($completed[$dependency])) {
            continue 2;
          }
        }
        $ordered[] = $step;
        $completed[$key] = true;
        unset($steps[$key]);
        $advanced = true;
      }
      if (!$advanced) {
        throw new LogicException('Missing or cyclic seed prerequisites.');
      }
    }

    $executor = new ORMExecutor($entityManager, new ORMPurger($entityManager));

    try {
      $executor->execute($ordered, $append);
    } finally {
      $entityManager->clear();
      $this->references->reset();
    }
  }
  // #endregion
}

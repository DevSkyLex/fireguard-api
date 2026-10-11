<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\{Executor\ORMExecutor, FixtureInterface, Loader, Purger\ORMPurger};
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Shared\Application\Port\Inbound\SeedStepPort;

use function array_merge;

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
    $ordered = $this->orderFixtures($fixtures);
    $executor = new ORMExecutor($entityManager, new ORMPurger($entityManager));

    try {
      $executor->execute($ordered, $append);
    } finally {
      $entityManager->clear();
      $this->references->reset();
    }
  }

  /**
   * Method orderFixtures
   *
   * Validates the capability graph before any fixture can purge or write to its database.
   *
   * @access private
   *
   * @param iterable<FixtureInterface> $fixtures owner-local fixtures and named capabilities
   *
   * @return array<array-key, FixtureInterface> dependency-ordered fixtures
   */
  private function orderFixtures(iterable $fixtures): array
  {
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

    return array_merge($legacy->getFixtures(), $this->orderSeedSteps($steps));
  }

  /**
   * Method orderSeedSteps
   *
   * Resolves named prerequisites in their registration order and refuses incomplete graphs.
   *
   * @access private
   *
   * @param array<string, FixtureInterface&SeedStepPort> $steps unexecuted capabilities
   *
   * @return list<FixtureInterface&SeedStepPort> dependency-ordered capabilities
   */
  private function orderSeedSteps(array $steps): array
  {
    $ordered = [];
    $completed = [];
    while ([] !== $steps) {
      $advanced = false;
      foreach ($steps as $key => $step) {
        if (!$this->dependenciesCompleted($step, $completed)) {
          continue;
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


    return $ordered;
  }

  /**
   * Method dependenciesCompleted
   *
   * Checks prerequisite identities without loading or invoking their owner fixtures.
   *
   * @access private
   *
   * @param SeedStepPort $step capability waiting to execute
   * @param array<string, true> $completed resolved capabilities
   *
   * @return bool whether all declared prerequisites are resolved
   */
  private function dependenciesCompleted(SeedStepPort $step, array $completed): bool
  {
    foreach ($step->dependencies() as $dependency) {
      if (!isset($completed[$dependency])) {
        return false;
      }
    }

    return true;
  }
  // #endregion
}

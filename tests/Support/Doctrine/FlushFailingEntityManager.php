<?php

declare(strict_types=1);

namespace Tests\Support\Doctrine;

use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Test double FlushFailingEntityManager.
 *
 * A transparent entity-manager decorator that injects a single flush()
 * failure. Everything else — getRepository(), find(), persist(), clear() —
 * runs against the real, transactional test database, so the code under test
 * still talks to PostgreSQL; only the one write that must blow up does.
 *
 * This exists because the failure arms it covers (a non-duplicate driver error
 * bubbling out of a save(), and the concurrent-insert recovery path) cannot be
 * provoked from SQL without aborting the surrounding DAMA transaction, which
 * would poison every later assertion in the same test.
 *
 * @category Test Doubles
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FlushFailingEntityManager extends EntityManagerDecorator
{
  // #region Properties
  /**
   * Property failed.
   *
   * @since 1.0.0
   */
  private bool $failed = false;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $wrapped the real entity manager
   * @param Throwable $failure the failure raised by the first flush() call
   * @param ?callable(): void $beforeFailure hook run just before the failure is raised
   */
  public function __construct(
    EntityManagerInterface $wrapped,
    private readonly Throwable $failure,
    private $beforeFailure = null,
  ) {
    parent::__construct($wrapped);
  }
  // #endregion

  // #region Methods
  /**
   * Method flush.
   *
   * Raises the configured failure once, then delegates to the real manager.
   *
   * @since 1.0.0
   *
   * @throws Throwable the configured failure, on the first call only
   */
  public function flush(): void
  {
    if (!$this->failed) {
      $this->failed = true;

      if (null !== $this->beforeFailure) {
        ($this->beforeFailure)();
      }

      throw $this->failure;
    }

    $this->wrapped->flush();
  }
  // #endregion
}

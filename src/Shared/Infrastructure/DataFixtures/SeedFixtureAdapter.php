<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\{FixtureInterface, ReferenceRepository, SharedFixtureInterface};
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Shared\Application\Contract\Seed\SeedReference;
use Shared\Application\Port\Inbound\{SeedReferenceConsumerPort, SeedStepPort};

use function get_object_vars;
use function is_string;

/**
 * Class SeedFixtureAdapter
 *
 * Adapts one local Doctrine fixture to a named pure seed capability at the composition root.
 *
 * @category Adapter
 */
final class SeedFixtureAdapter implements FixtureInterface, SharedFixtureInterface, SeedStepPort
{
  // #region Properties
  /**
   * Reference repository managed by the owning database's fixture executor.
   */
  private ?ReferenceRepository $referenceRepository = null;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param FixtureInterface $fixture private owner-local seed implementation
   * @param ObjectManager $manager explicitly selected owning database
   * @param SeedReferenceRegistryAdapter $references completed scalar projections
   * @param string $seedKey stable capability identity
   * @param list<string> $prerequisites required capabilities
   *
   * @return void
   */
  public function __construct(private readonly FixtureInterface $fixture, private readonly ObjectManager $manager, private readonly SeedReferenceRegistryAdapter $references, private readonly string $seedKey, private readonly array $prerequisites = [])
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function key(): string
  {
    return $this->seedKey;
  }

  /**
   * {@inheritdoc}
   */
  public function dependencies(): array
  {
    return $this->prerequisites;
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceRepository(ReferenceRepository $referenceRepository): void
  {
    $this->referenceRepository = $referenceRepository;
    if ($this->fixture instanceof SharedFixtureInterface) {
      $this->fixture->setReferenceRepository($referenceRepository);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function load(ObjectManager $manager): void
  {
    if ($manager !== $this->manager) {
      throw new LogicException('Seed capability invoked with a different owning database.');
    }
    $this->references->publish($this->seed());
  }

  /**
   * {@inheritdoc}
   */
  public function seed(): array
  {
    if (null === $this->referenceRepository) {
      throw new LogicException('Seed capability requires the owning fixture executor.');
    }
    if ($this->fixture instanceof SeedReferenceConsumerPort) {
      $this->fixture->setSeedReferences($this->references);
    }
    $this->fixture->load($this->manager);

    return $this->projectReferences($this->referenceRepository);
  }

  /**
   * Method projectReferences
   *
   * Publishes named scalar snapshots without exposing owner-local Records to other fixtures.
   *
   * @access private
   *
   * @param ReferenceRepository $references loaded references from the owning executor
   *
   * @return array<string, SeedReference> the permitted fixture projections
   */
  private function projectReferences(ReferenceRepository $references): array
  {
    $projections = [];
    foreach ($references->getReferencesByClass() as $objects) {
      foreach ($objects as $name => $object) {
        if (!is_string($name)) {
          continue;
        }
        $projection = $this->projectReference($object);
        if (null !== $projection) {
          $projections[$name] = $projection;
        }
      }
    }

    return $projections;
  }

  /**
   * Method projectReference
   *
   * Selects only the published seed facts from public Record fields.
   *
   * @access private
   *
   * @param object $object the owner-local reference
   *
   * @return ?SeedReference the scalar snapshot, or null without a string identifier
   */
  private function projectReference(object $object): ?SeedReference
  {
    $fields = get_object_vars($object);
    if (!is_string($fields['id'] ?? null)) {
      return null;
    }

    return new SeedReference(
      id: $fields['id'],
      type: is_string($fields['type'] ?? null) ? $fields['type'] : '',
      status: is_string($fields['status'] ?? null) ? $fields['status'] : '',
      facilityId: is_string($fields['facilityId'] ?? null) ? $fields['facilityId'] : null,
      name: is_string($fields['name'] ?? null) ? $fields['name'] : null,
    );
  }
  // #endregion
}

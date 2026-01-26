<?php

declare(strict_types=1);

namespace Tests\Integration\Tenant\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};
use Tenant\Infrastructure\Persistence\Doctrine\Repository\TenantRepository;

/**
 * Test TenantRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TenantRepository::class)]
final class TenantRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private TenantRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new TenantRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindById(): void
  {
    $tenant = $this->createTenant(
      id: '123e4567-e89b-12d3-a456-426614174000',
      name: 'Alpha',
    );

    $this->repository->save($tenant);

    $found = $this->repository->findById(new TenantId('123e4567-e89b-12d3-a456-426614174000'));

    self::assertNotNull($found);
    self::assertSame('Alpha', (string) $found->name());
  }

  #[Test]
  public function testSaveUpdatesExistingTenant(): void
  {
    $tenant = $this->createTenant(
      id: '123e4567-e89b-12d3-a456-426614174010',
      name: 'Beta',
    );
    $this->repository->save($tenant);

    $tenant->deactivate();
    $tenant->updateSettings(new TenantSettings(accessTokenTtl: 900));
    $this->repository->save($tenant);

    $found = $this->repository->findById(new TenantId('123e4567-e89b-12d3-a456-426614174010'));

    self::assertNotNull($found);
    self::assertFalse($found->isActive());
    self::assertSame(900, $found->settings()->accessTokenTtl);
  }

  #[Test]
  public function testFindAllReturnsActiveTenants(): void
  {
    $tenantOne = $this->createTenant(
      id: '123e4567-e89b-12d3-a456-426614174020',
      name: 'Gamma',
    );
    $tenantTwo = $this->createTenant(
      id: '123e4567-e89b-12d3-a456-426614174021',
      name: 'Delta',
    );
    $tenantTwo->deactivate();

    $this->repository->save($tenantOne);
    $this->repository->save($tenantTwo);

    $tenants = $this->repository->findAll();

    self::assertCount(1, $tenants);
    self::assertSame('Gamma', (string) $tenants[0]->name());
  }

  #[Test]
  public function testDeleteRemovesTenant(): void
  {
    $tenant = $this->createTenant(
      id: '123e4567-e89b-12d3-a456-426614174030',
      name: 'Epsilon',
    );

    $this->repository->save($tenant);

    $this->repository->delete(new TenantId('123e4567-e89b-12d3-a456-426614174030'));

    self::assertNull($this->repository->findById(new TenantId('123e4567-e89b-12d3-a456-426614174030')));
  }
  // #endregion

  // #region Helpers
  private function createTenant(string $id, string $name): Tenant
  {
    return Tenant::create(
      id: new TenantId($id),
      name: new TenantName($name),
      settings: new TenantSettings(),
    );
  }
  // #endregion
}

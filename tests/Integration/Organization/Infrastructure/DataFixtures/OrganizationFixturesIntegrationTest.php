<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(className: OrganizationFixtures::class)]
final class OrganizationFixturesIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testLoadPersistsOrganizationGraphAndReferences(): void
  {
    /** @var OrganizationFixtures $organizationFixtures */
    $organizationFixtures = static::getContainer()->get(OrganizationFixtures::class);

    $loader = new Loader();
    $loader->addFixture($organizationFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    self::assertSame(1, $this->entityManager->getRepository(OrganizationRecord::class)->count([]));
    self::assertSame(3, $this->entityManager->getRepository(OrganizationRoleRecord::class)->count([]));
    self::assertSame(2, $this->entityManager->getRepository(OrganizationMemberRecord::class)->count([]));
    self::assertSame(2, $this->entityManager->getRepository(OrganizationMemberRoleRecord::class)->count([]));
    self::assertSame(1, $this->entityManager->getRepository(OrganizationInvitationRecord::class)->count([]));

    self::assertTrue($organizationFixtures->hasReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class));
    self::assertTrue($organizationFixtures->hasReference(OrganizationFixtures::ADMIN_ROLE_REFERENCE, OrganizationRoleRecord::class));

    /** @var OrganizationRecord $organization */
    $organization = $organizationFixtures->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $organization->id);
    self::assertSame('fireguard-seed-organization', $organization->slug);
  }
}

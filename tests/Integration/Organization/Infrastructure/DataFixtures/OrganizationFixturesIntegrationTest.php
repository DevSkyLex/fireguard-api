<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, TeamMemberRecord, TeamRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;
use function explode;

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

    // The main "Fireguard Seed Organization" plus its four lightweight
    // secondary tenants.
    self::assertSame(1 + count(OrganizationFixtures::SECONDARY_ORGANIZATION_SEEDS), $this->entityManager->getRepository(OrganizationRecord::class)->count([]));
    // 3 for the main org (admin/member/inspector) plus 2 (admin/member) per
    // secondary org.
    self::assertSame(3 + 2 * count(OrganizationFixtures::SECONDARY_ORGANIZATION_SEEDS), $this->entityManager->getRepository(OrganizationRoleRecord::class)->count([]));
    // The owner and the inspector, plus the seeded workforce and the bulk
    // pool, plus one owner per secondary org and their extra cross-org members.
    $secondaryMemberCount = count(OrganizationFixtures::SECONDARY_ORGANIZATION_SEEDS);
    foreach (OrganizationFixtures::SECONDARY_ORGANIZATION_SEEDS as $secondarySeed) {
      $secondaryMemberCount += '' === $secondarySeed['extraMemberIndexes'] ? 0 : count(explode(',', $secondarySeed['extraMemberIndexes']));
    }
    $memberCount = 2 + count(OrganizationFixtures::STAFF_MEMBER_SEEDS) + OrganizationFixtures::BULK_MEMBER_COUNT + $secondaryMemberCount;
    self::assertSame($memberCount, $this->entityManager->getRepository(OrganizationMemberRecord::class)->count([]));
    self::assertSame($memberCount, $this->entityManager->getRepository(OrganizationMemberRoleRecord::class)->count([]));
    self::assertSame(3, $this->entityManager->getRepository(OrganizationInvitationRecord::class)->count([]));
    self::assertSame(count(OrganizationFixtures::TEAM_SEEDS), $this->entityManager->getRepository(TeamRecord::class)->count([]));
    self::assertSame(10, $this->entityManager->getRepository(TeamMemberRecord::class)->count([]));
    // Three of the seeded accounts are departed, locked or unverified: they
    // stay on the roster but must not be assignable.
    self::assertSame(3, $this->entityManager->getRepository(OrganizationMemberRecord::class)->count(['isActive' => false]));

    self::assertTrue($organizationFixtures->hasReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class));
    self::assertTrue($organizationFixtures->hasReference(OrganizationFixtures::ADMIN_ROLE_REFERENCE, OrganizationRoleRecord::class));

    /** @var OrganizationRecord $organization */
    $organization = $organizationFixtures->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);
    self::assertSame(OrganizationFixtures::ORGANIZATION_ID, $organization->id);
    self::assertSame('fireguard-seed-organization', $organization->slug);
  }
}

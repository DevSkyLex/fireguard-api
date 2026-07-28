<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\Client\ClientMapper;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ClientFixturesIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientFixtures::class)]
final class ClientFixturesIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testLoadPersistsClientsAndReferences(): void
  {
    $fixtures = new ClientFixtures(new ClientMapper());

    // Purge before loading: the test databases carry the seeded baseline, so
    // loading on top of it would collide on primary keys and make the count
    // below meaningless. The purge is undone with the rest of the test by
    // DAMA's transaction rollback.
    new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager))->execute([$fixtures], false);

    $count = $this->entityManager->getRepository(ClientRecord::class)->count([]);
    self::assertSame(4, $count);

    self::assertTrue($fixtures->hasReference(ClientFixtures::WEB_CLIENT_REFERENCE, ClientRecord::class));
    self::assertTrue($fixtures->hasReference(ClientFixtures::MOBILE_CLIENT_REFERENCE, ClientRecord::class));
    self::assertTrue($fixtures->hasReference(ClientFixtures::API_CLIENT_REFERENCE, ClientRecord::class));
    self::assertTrue($fixtures->hasReference(ClientFixtures::DEV_CLIENT_REFERENCE, ClientRecord::class));

    /** @var ClientRecord $devClient */
    $devClient = $fixtures->getReference(ClientFixtures::DEV_CLIENT_REFERENCE, ClientRecord::class);

    self::assertSame('a7b8c9d0-e1f2-4456-8123-789012345678', $devClient->id->toRfc4122());
  }
  // #endregion
}

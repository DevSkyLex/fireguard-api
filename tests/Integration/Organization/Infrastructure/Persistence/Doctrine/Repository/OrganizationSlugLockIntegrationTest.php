<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test OrganizationSlugLockIntegrationTest.
 *
 * @category Repository Tests
 */
#[CoversClass(OrganizationRepository::class)]
final class OrganizationSlugLockIntegrationTest extends KernelTestCase
{
  #[Test]
  public function testSlugNamespaceIsExclusiveUntilTheCreationTransactionEnds(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $parameters = $manager->getConnection()->getParams();
    unset($parameters['driverClass']);
    $parameters['driver'] = 'pdo_pgsql';
    $writer = DriverManager::getConnection($parameters);
    $competitor = DriverManager::getConnection($parameters);

    try {
      $writer->beginTransaction();
      $writerManager = $this->createStub(EntityManagerInterface::class);
      $writerManager->method('getConnection')->willReturn($writer);
      new OrganizationRepository($writerManager)->lockSlugNamespace();
      $competitor->beginTransaction();
      self::assertFalse((bool) $competitor->fetchOne("SELECT pg_try_advisory_xact_lock(hashtextextended('organization.slug', 0))"));
      $writer->commit();
      self::assertTrue((bool) $competitor->executeQuery("SELECT pg_try_advisory_xact_lock(hashtextextended('organization.slug', 0))")->fetchOne());
      $competitor->commit();
    } finally {
      if ($writer->isTransactionActive()) {
        $writer->rollBack();
      }
      if ($competitor->isTransactionActive()) {
        $competitor->rollBack();
      }
      $writer->close();
      $competitor->close();
    }
  }
}

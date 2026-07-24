<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Adapter\Billing;

use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Infrastructure\Adapter\Billing\OrganizationPlanAssignmentAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Repository\PlanRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test OrganizationPlanAssignmentAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationPlanAssignmentAdapter::class)]
final class OrganizationPlanAssignmentAdapterIntegrationTest extends KernelTestCase
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
  public function testAssignPlanByKeyThrowsWhenPlanKeyIsUnknown(): void
  {
    // Real repository lookup against the database returns no match, so the
    // adapter must reject the assignment before ever touching the command bus.
    $commandBus = self::createStub(CommandBusPort::class);
    $adapter = new OrganizationPlanAssignmentAdapter(
      $commandBus,
      new PlanRepository($this->entityManager),
    );

    $this->expectException(PlanNotFoundException::class);

    $adapter->assignPlanByKey('f4000000-0000-4000-8000-000000000001', 'plan-assignment-unknown-key');
  }
}

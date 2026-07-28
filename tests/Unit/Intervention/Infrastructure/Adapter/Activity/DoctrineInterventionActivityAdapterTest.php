<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Activity;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Adapter\Activity\DoctrineInterventionActivityAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

/**
 * Test DoctrineInterventionActivityAdapterTest.
 *
 * Complements the integration coverage: the intervention can be deleted
 * between the handler's authorization check and the activity append, and the
 * adapter must surface that as a domain not-found rather than an ORM error.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionActivityAdapter::class)]
final class DoctrineInterventionActivityAdapterTest extends TestCase
{
  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655446001';

  // #region Methods
  #[Test]
  public function testAppendReportsAVanishedInterventionAsNotFound(): void
  {
    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);
    $entityManager->expects(self::never())->method('persist');
    $entityManager->expects(self::never())->method('flush');

    // The view mapper is final, so it is built for real over stubbed ports;
    // the append never reaches it on this path.
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $adapter = new DoctrineInterventionActivityAdapter(
      $entityManager,
      $this->createStub(UuidFactory::class),
      new InterventionViewMapper($entityManager, $resources, new InterventionIssueFinder($resources)),
    );

    $this->expectException(InterventionNotFoundException::class);

    $adapter->append(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      null,
      'comment',
      'comment',
      'Looks good.',
      null,
    );
  }
  // #endregion
}

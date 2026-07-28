<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Recurrence;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Domain\Exception\InterventionNotFoundException;
use Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecurrenceRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Shared\Application\Factory\UuidFactory;

/**
 * Test DoctrineInterventionRecurrenceAdapterTest.
 *
 * Complements the integration coverage with the two association guards. Both
 * associations are `nullable: false` in the mapping, but a recurrence read
 * back after its organization or template was removed would otherwise
 * dereference null while building the view.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionRecurrenceAdapter::class)]
final class DoctrineInterventionRecurrenceAdapterTest extends TestCase
{
  // #region Methods
  /**
   * @return iterable<string, array{string, string}>
   */
  public static function missingAssociationProvider(): iterable
  {
    yield 'organization' => ['organizationId', 'Intervention recurrence organization is missing.'];

    yield 'template' => ['templateId', 'Intervention recurrence template is missing.'];
  }

  #[Test]
  #[DataProvider('missingAssociationProvider')]
  public function testAnUnresolvableAssociationIsReportedAsNotFound(string $reader, string $message): void
  {
    $adapter = new DoctrineInterventionRecurrenceAdapter(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(UuidFactory::class),
    );

    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage($message);

    new ReflectionMethod($adapter, $reader)->invoke($adapter, new InterventionRecurrenceRecord());
  }
  // #endregion
}

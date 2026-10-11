<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventDispatcher;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Shared\Domain\ValueObject\Uuid;
use Shared\Infrastructure\EventDispatcher\ReflectionIntegrationEventSnapshotAdapter;
use Stringable;

use function class_exists;
use function count;
use function is_array;

/**
 * Class IntegrationEventContractsTest
 *
 * Checks every published legacy conversion for complete snapshots and signatures independent of private Domain types.
 *
 * @category Unit Test
 */
final class IntegrationEventContractsTest extends TestCase
{
  // #region Constants
  /**
   * Constant OWNERS
   *
   * Owners publishing the historical integration events covered by this migration.
   *
   * @var list<string>
   */
  private const array OWNERS = [
    'Approval', 'Assistant', 'Audit', 'Auth', 'Automation', 'Calendar', 'Compliance', 'Customer',
    'Equipment', 'Facility', 'Import', 'Inspection', 'Intervention', 'Maintenance', 'MaintenanceExport',
    'Messaging', 'OAuth', 'Onboarding', 'Organization', 'Otp', 'Procurement', 'ServiceRequest', 'User', 'Webhook',
  ];
  // #endregion

  // #region Tests
  /**
   * Method testOwnerApprovedSnapshotsAreCompleteAndPure
   *
   * Confirms immutable conversion preserves every property without exposing private types in public signatures.
   *
   * @access public
   *
   * @param class-string $legacy supported historical event class
   * @param class-string $public owner-published counterpart
   *
   * @return void
   */
  #[Test]
  #[DataProvider('contracts')]
  public function testOwnerApprovedSnapshotsAreCompleteAndPure(string $legacy, string $public): void
  {
    $source = new ReflectionClass($legacy);
    $event = $source->newInstanceWithoutConstructor();
    $occurredAt = new DateTimeImmutable('2024-01-02T03:04:05+00:00');
    $identifier = new Uuid('00000000-0000-4000-a000-000000000042');
    foreach ($source->getProperties() as $property) {
      if ($property->isStatic()) {
        continue;
      }
      $type = $property->getType();
      self::assertInstanceOf(ReflectionNamedType::class, $type);
      $value = match ($type->getName()) {
        'string' => 'snapshot-' . $property->getName(),
        'int' => 3,
        'bool' => true,
        'array' => [],
        DateTimeImmutable::class => $occurredAt,
        Uuid::class => $identifier,
        default => self::fail('Unexpected legacy property type: ' . $type->getName()),
      };
      $property->setValue($event, $value);
    }
    $target = new ReflectionClass($public);
    $converted = new ReflectionIntegrationEventSnapshotAdapter()->copy($event, $public);
    self::assertInstanceOf($public, $converted);
    self::assertCount($source->getProperties() ? count($source->getProperties()) : 0, $target->getProperties());
    foreach ($target->getProperties() as $property) {
      if ($property->isStatic()) {
        continue;
      }
      $type = $property->getType();
      self::assertInstanceOf(ReflectionNamedType::class, $type);
      self::assertTrue($type->isBuiltin() || DateTimeImmutable::class === $type->getName(), 'Public event property exposes ' . $type->getName());
      $expected = $source->getProperty($property->getName())->getValue($event);
      if ('string' === $type->getName() && $expected instanceof Stringable) {
        $expected = (string) $expected;
      }
      self::assertSame($expected, $property->getValue($converted));
    }
    foreach ($target->getMethods() as $method) {
      $returnType = $method->getReturnType();
      if ($returnType instanceof ReflectionNamedType) {
        self::assertTrue($returnType->isBuiltin() || DateTimeImmutable::class === $returnType->getName());
      }
      foreach ($method->getParameters() as $parameter) {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType) {
          self::assertTrue($type->isBuiltin() || DateTimeImmutable::class === $type->getName());
        }
      }
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method contracts
   *
   * Reads the exact owner-approved maps rather than guessing targets from class names.
   *
   * @access public
   *
   * @return iterable<string, array{class-string, class-string}> historical/public pairs
   */
  public static function contracts(): iterable
  {
    foreach (self::OWNERS as $owner) {
      $converterClass = $owner . '\\Infrastructure\\Adapter\\Event\\IntegrationEventConverterAdapter';
      self::assertTrue(class_exists($converterClass));
      $converter = new ReflectionClass($converterClass);
      $contracts = $converter->getConstant('CONTRACTS');
      self::assertTrue(is_array($contracts));
      foreach ($contracts as $legacy => $public) {
        self::assertIsString($legacy);
        self::assertIsString($public);
        self::assertTrue(class_exists($legacy));
        self::assertTrue(class_exists($public));
        yield $legacy => [$legacy, $public];
      }
    }
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\FacilityModel;

use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\FacilityModelTransform;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use const INF;
use const NAN;

/**
 * Test FacilityModelTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModelTest extends TestCase
{
  #[Test]
  public function independentlyRevisionsAssociationsAndActivationWithoutChangingTheFile(): void
  {
    $model = $this->model();
    $path = $model->storagePath;
    $model->changeSettings(new FacilityModelTransform(2, 90, 1, -2, 3), [
      ['nodeIndex' => 0, 'facilityId' => $model->buildingId],
      ['nodeIndex' => 1, 'facilityId' => $model->buildingId],
    ]);
    self::assertSame(2, $model->revision);
    self::assertSame(2.0, $model->transform->scale);
    self::assertCount(2, $model->bindings);
    $model->activate();
    self::assertTrue($model->active);
    self::assertSame(3, $model->revision);
    self::assertSame($path, $model->storagePath);
    self::assertSame([], $this->model()->bindings);
  }

  #[Test]
  public function refusesDuplicateNodeAssociations(): void
  {
    $model = $this->model();
    $this->expectException(FacilityModelException::class);
    $model->changeSettings(new FacilityModelTransform(), [
      ['nodeIndex' => 0, 'facilityId' => $model->buildingId],
      ['nodeIndex' => 0, 'facilityId' => $model->buildingId],
    ]);
  }

  /**
   * @param array<array-key, mixed> $indices
   */
  #[Test]
  #[DataProvider('badRemovedNodes')]
  public function rejectsInvalidExplicitRemovalWithoutChangingRevision(array $indices): void
  {
    $model = $this->model();

    try {
      $model->changeSettings(new FacilityModelTransform(), [], $indices);
      self::fail('Invalid removal indices must be rejected.');
    } catch (FacilityModelException) {
      self::assertSame(1, $model->revision);
      self::assertSame([], $model->bindings);
    }
  }

  /**
   * @return iterable<string, array{array<array-key, mixed>}>
   */
  public static function badRemovedNodes(): iterable
  {
    yield 'negative' => [[-1]];
    yield 'outside nodes' => [[2]];
    yield 'string' => [['0']];
    yield 'duplicate' => [[0, 0]];
    yield 'not a list' => [['key' => 0]];
  }

  #[Test]
  public function refusesRemovingAndAssigningTheSameNode(): void
  {
    $model = $this->model();
    $this->expectExceptionMessage('A node association cannot be removed and assigned together.');
    $model->changeSettings(new FacilityModelTransform(), [['nodeIndex' => 0, 'facilityId' => $model->buildingId]], [0]);
  }

  #[Test]
  #[DataProvider('badTransforms')]
  public function rejectsNonFiniteOrNonPositiveTransform(float $scale, float $rotation): void
  {
    $this->expectException(FacilityModelException::class);
    new FacilityModelTransform($scale, $rotation);
  }

  /**
   * @return iterable<array{float, float}>
   */
  public static function badTransforms(): iterable
  {
    yield [0.0, 0.0];
    yield [-1.0, 0.0];
    yield [INF, 0.0];
    yield [1.0, NAN];
  }

  private function model(): FacilityModel
  {
    return FacilityModel::create(
      '980e8400-e29b-41d4-a716-446655470001',
      '980e8400-e29b-41d4-a716-446655470002',
      '980e8400-e29b-41d4-a716-446655470003',
      'Building.glb',
      100,
      [['index' => 0, 'name' => 'shell'], ['index' => 1, 'name' => 'room']],
    );
  }
}

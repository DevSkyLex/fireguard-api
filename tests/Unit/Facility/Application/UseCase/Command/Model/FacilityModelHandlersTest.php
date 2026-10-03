<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Model;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\{FacilityModelRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Facility\Application\Service\{FacilityModelAccessGuard, FacilitySpatialValidityResolver};
use Facility\Application\UseCase\Command\Model\ActivateFacilityModel\{ActivateFacilityModelCommand, ActivateFacilityModelHandler};
use Facility\Application\UseCase\Command\Model\DeleteFacilityModel\{DeleteFacilityModelCommand, DeleteFacilityModelHandler};
use Facility\Application\UseCase\Command\Model\UpdateFacilityModel\{UpdateFacilityModelCommand, UpdateFacilityModelHandler};
use Facility\Application\UseCase\Command\Model\UploadFacilityModel\{UploadFacilityModelCommand, UploadFacilityModelHandler};
use Facility\Application\UseCase\Query\Model\DownloadFacilityModel\{DownloadFacilityModelHandler, DownloadFacilityModelQuery};
use Facility\Application\UseCase\Query\Model\GetFacilityModel\{GetFacilityModelHandler, GetFacilityModelQuery};
use Facility\Application\UseCase\Query\Model\ListFacilityModels\{ListFacilityModelsHandler, ListFacilityModelsQuery};
use Facility\Domain\Exception\FacilityModelException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{FileStoragePort, UuidGeneratorPort};
use Tests\Helper\GlbFixture;

/**
 * Test FacilityModelHandlersTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModelHandlersTest extends TestCase
{
  private const string ORG = 'ae0e8400-e29b-41d4-a716-446655470001';

  private const string BUILDING = 'ae0e8400-e29b-41d4-a716-446655470002';

  private const string MODEL = 'ae0e8400-e29b-41d4-a716-446655470003';

  private const string ROOM = 'ae0e8400-e29b-41d4-a716-446655470004';

  #[Test]
  public function uploadValidatesBeforeWritingAndReturnsAnIndependentDraft(): void
  {
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('findByBuilding')->willReturn([]);
    $models->expects(self::once())->method('insert')->with(self::callback(function (FacilityModel $model): bool {
      self::assertSame([], $model->bindings);
      self::assertFalse($model->active);
      self::assertSame(self::ORG, $model->organizationId);

      return true;
    }));
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with('facility-model/' . self::BUILDING . '/' . self::MODEL . '/model.glb', GlbFixture::contents());
    $storage->expects(self::never())->method('delete');
    $handler = new UploadFacilityModelHandler($this->access($models), $models, $storage, $this->uuids());
    $result = $handler(new UploadFacilityModelCommand('member', self::ORG, self::BUILDING, 'Model.glb', GlbFixture::contents()));
    self::assertSame(self::MODEL, $result->model->id);
    self::assertSame(1, $result->model->revision);
  }

  #[Test]
  public function invalidContentNeverReachesStorage(): void
  {
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('findByBuilding')->willReturn([]);
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $handler = new UploadFacilityModelHandler($this->access($models), $models, $storage, $this->uuids());
    $this->expectException(FacilityModelException::class);
    $handler(new UploadFacilityModelCommand('member', self::ORG, self::BUILDING, 'Bad.glb', 'invalid'));
  }

  #[Test]
  public function persistenceFailureCompensatesForTheImmutableFile(): void
  {
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('findByBuilding')->willReturn([]);
    $models->expects(self::once())->method('insert')->willThrowException(new RuntimeException('database failed'));
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write');
    $storage->expects(self::once())->method('delete')->with('facility-model/' . self::BUILDING . '/' . self::MODEL . '/model.glb');
    $handler = new UploadFacilityModelHandler($this->access($models), $models, $storage, $this->uuids());
    $this->expectExceptionMessage('database failed');
    $handler(new UploadFacilityModelCommand('member', self::ORG, self::BUILDING, 'Model.glb', GlbFixture::contents()));
  }

  #[Test]
  public function updatePassesTheExpectedRevisionAndValidatesSameBuildingBindings(): void
  {
    $model = $this->model();
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('update')->with($model, 1);
    $handler = new UpdateFacilityModelHandler($this->access($models), $models);
    $result = $handler(new UpdateFacilityModelCommand('member', self::MODEL, 1, $model->transform->toArray(), [['nodeIndex' => 0, 'facilityId' => self::BUILDING]]));
    self::assertSame(2, $result->model->revision);
    self::assertSame([['nodeIndex' => 0, 'facilityId' => self::BUILDING]], $result->model->bindings);
  }

  #[Test]
  public function staleUpdateNeverPersists(): void
  {
    $model = $this->model();
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::never())->method('update');
    $handler = new UpdateFacilityModelHandler($this->access($models), $models);
    $this->expectExceptionMessage('The resource revision is stale.');
    $handler(new UpdateFacilityModelCommand('member', self::MODEL, 9, $model->transform->toArray(), []));
  }

  #[Test]
  public function activateDelegatesTheAtomicReplacementWithTheExpectedRevision(): void
  {
    $model = $this->model();
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('activate')->with($model, 1);
    $result = new ActivateFacilityModelHandler($this->access($models), $models)(new ActivateFacilityModelCommand('member', self::MODEL, 1));
    self::assertSame(2, $result->model->revision);
    self::assertTrue($result->model->active);
  }

  #[Test]
  public function deletionPersistsBeforeRemovingTheStoredFile(): void
  {
    $model = $this->model();
    $deleted = false;
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('delete')->with($model, 1)->willReturnCallback(static function () use (&$deleted): void { $deleted = true; });
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with($model->storagePath)->willReturnCallback(static function () use (&$deleted): void { self::assertTrue($deleted); });
    $result = new DeleteFacilityModelHandler($this->access($models), $models, $storage)(new DeleteFacilityModelCommand('member', self::MODEL, 1));
    self::assertSame(self::MODEL, $result->id);
  }

  #[Test]
  public function queriesReturnMetadataListsAndAuthenticatedBytes(): void
  {
    $model = $this->model();
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->method('findByBuilding')->willReturn([$model]);
    $guard = $this->access($models);
    $get = new GetFacilityModelHandler($guard)(new GetFacilityModelQuery('member', self::MODEL));
    self::assertSame(self::MODEL, $get->model->id);
    $list = new ListFacilityModelsHandler($guard, $models)(new ListFacilityModelsQuery('member', self::ORG, self::BUILDING));
    self::assertSame(self::MODEL, $list->models[0]->id);
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('read')->with($model->storagePath)->willReturn('immutable bytes');
    $download = new DownloadFacilityModelHandler($guard, $storage)(new DownloadFacilityModelQuery('member', self::MODEL));
    self::assertSame('immutable bytes', $download->contents);
    self::assertSame('Building.glb', $download->fileName);
  }

  #[Test]
  public function outsideScopeReadsHaveTheSameFailureAsAnUnknownModel(): void
  {
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($this->model());
    $handler = new GetFacilityModelHandler($this->access($models, OrganizationAccessDecision::OUTSIDE_SCOPE));
    $this->expectExceptionMessage('Facility model not found.');
    $handler(new GetFacilityModelQuery('outsider', self::MODEL));
  }

  #[Test]
  public function readsMaskUnavailableTargetsAndLeaveStoredActiveBindingsIntact(): void
  {
    $model = $this->model();
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM]];
    $model->changeSettings($model->transform, $bindings);
    $model->activate();
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $result = new GetFacilityModelHandler($this->access($models))(new GetFacilityModelQuery('member', self::MODEL));
    self::assertTrue($result->model->active);
    self::assertSame([], $result->model->bindings);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $result->model->bindingIssues);
    self::assertSame($bindings, $model->bindings);
    self::assertSame(3, $model->revision);
  }

  #[Test]
  public function listsResolveThePublishedSubtreeOnceForAllModelsAndKeepArchivedTargets(): void
  {
    $room = Facility::create(FacilityId::fromString(self::ROOM), FacilityOrganizationId::fromString(self::ORG), FacilityType::ZONE, new FacilityName('Archived room'));
    $room->archive();
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('findByBuilding')->willReturn([$model, clone $model]);
    $facilities = $this->createMock(FacilityRepositoryPort::class);
    $facilities->method('findPublishedById')->willReturn($this->building());
    $facilities->expects(self::once())->method('findDescendants')->with(FacilityOrganizationId::fromString(self::ORG), FacilityId::fromString(self::BUILDING), true)->willReturn([$room]);
    $result = new ListFacilityModelsHandler($this->access($models, facilities: $facilities), $models)(new ListFacilityModelsQuery('member', self::ORG, self::BUILDING));
    self::assertCount(2, $result->models);
    foreach ($result->models as $view) {
      self::assertSame([['nodeIndex' => 0, 'facilityId' => self::ROOM]], $view->bindings);
      self::assertSame([], $view->bindingIssues);
    }
  }

  #[Test]
  public function transformOnlyUpdatePreservesUnavailableBindings(): void
  {
    $model = $this->model();
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM]];
    $model->changeSettings($model->transform, $bindings);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('update')->with(self::callback(static function (FacilityModel $model) use ($bindings): bool {
      self::assertSame($bindings, $model->bindings);
      self::assertSame(2.0, $model->transform->scale);

      return true;
    }), 2);
    $transform = $model->transform->toArray();
    $transform['scale'] = 2;
    $result = new UpdateFacilityModelHandler($this->access($models), $models)(new UpdateFacilityModelCommand('member', self::MODEL, 2, $transform));
    self::assertSame([], $result->model->bindings);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $result->model->bindingIssues);
  }

  #[Test]
  public function changingAUsableAssociationDoesNotDropAnotherMaskedNode(): void
  {
    $model = $this->model([['index' => 0, 'name' => 'missing'], ['index' => 1, 'name' => 'shell']]);
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('update')->with(self::callback(static function (FacilityModel $model): bool {
      self::assertSame([['nodeIndex' => 1, 'facilityId' => self::BUILDING], ['nodeIndex' => 0, 'facilityId' => self::ROOM]], $model->bindings);

      return true;
    }), 2);
    $result = new UpdateFacilityModelHandler($this->access($models), $models)(new UpdateFacilityModelCommand('member', self::MODEL, 2, $model->transform->toArray(), [['nodeIndex' => 1, 'facilityId' => self::BUILDING]]));
    self::assertSame([['nodeIndex' => 1, 'facilityId' => self::BUILDING]], $result->model->bindings);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $result->model->bindingIssues);
  }

  #[Test]
  public function explicitRemovalClearsAMaskedAssociationWithoutRequiringItsHiddenTarget(): void
  {
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('update')->with(self::callback(static fn (FacilityModel $model): bool => [] === $model->bindings), 2);
    $result = new UpdateFacilityModelHandler($this->access($models), $models)(new UpdateFacilityModelCommand('member', self::MODEL, 2, $model->transform->toArray(), null, [0]));
    self::assertSame([], $result->model->bindings);
    self::assertSame([], $result->model->bindingIssues);
  }

  #[Test]
  public function anEmptyReplacementExplicitlyClearsAllHistoricalAssociations(): void
  {
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::once())->method('update')->with(self::callback(static fn (FacilityModel $model): bool => [] === $model->bindings), 2);
    $result = new UpdateFacilityModelHandler($this->access($models), $models)(new UpdateFacilityModelCommand('member', self::MODEL, 2, $model->transform->toArray(), []));
    self::assertSame([], $result->model->bindingIssues);
  }

  #[Test]
  public function replacingAMaskedAssociationWithANewUnavailableTargetIsRejected(): void
  {
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::never())->method('update');
    $this->expectException(FacilityModelException::class);
    new UpdateFacilityModelHandler($this->access($models), $models)(new UpdateFacilityModelCommand('member', self::MODEL, 2, $model->transform->toArray(), [['nodeIndex' => 0, 'facilityId' => 'ae0e8400-e29b-41d4-a716-446655470005']]));
  }

  #[Test]
  public function activationRefusesUnavailableHistoricalTargetsWithoutPersisting(): void
  {
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::ROOM]]);
    $models = $this->createMock(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $models->expects(self::never())->method('activate');
    $this->expectException(FacilityModelException::class);
    new ActivateFacilityModelHandler($this->access($models), $models)(new ActivateFacilityModelCommand('member', self::MODEL, 2));
  }

  #[Test]
  public function missingPublishedBuildingMasksAllTargetsWithoutWalkingAnotherSubtree(): void
  {
    $model = $this->model();
    $model->changeSettings($model->transform, [['nodeIndex' => 0, 'facilityId' => self::BUILDING]]);
    $models = $this->createStub(FacilityModelRepositoryPort::class);
    $models->method('find')->willReturn($model);
    $facilities = $this->createMock(FacilityRepositoryPort::class);
    $facilities->method('findPublishedById')->willReturn(null);
    $facilities->expects(self::never())->method('findDescendants');
    $result = new GetFacilityModelHandler($this->access($models, facilities: $facilities))(new GetFacilityModelQuery('member', self::MODEL));
    self::assertSame([], $result->model->bindings);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $result->model->bindingIssues);
  }

  private function access(FacilityModelRepositoryPort $models, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED, ?FacilityRepositoryPort $facilities = null): FacilityModelAccessGuard
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);
    if (null === $facilities) {
      $facilities = $this->createStub(FacilityRepositoryPort::class);
      $facilities->method('findPublishedById')->willReturn($this->building());
      $facilities->method('findDescendants')->willReturn([]);
    }

    $spatialRead = $this->createStub(FacilitySpatialReadPort::class);
    $spatialRead->method('readContext')->willReturn(new FacilitySpatialContext([
      self::BUILDING => ['parentId' => null, 'type' => 'building', 'recordStatus' => 'published'],
      self::ROOM => ['parentId' => self::BUILDING, 'type' => 'zone', 'recordStatus' => 'published'],
    ]));

    return new FacilityModelAccessGuard($authorization, $facilities, $models, new FacilitySpatialValidityResolver($spatialRead));
  }

  private function uuids(): UuidGeneratorPort
  {
    $uuids = $this->createStub(UuidGeneratorPort::class);
    $uuids->method('generate')->willReturn(self::MODEL);

    return $uuids;
  }

  private function building(): Facility
  {
    return Facility::create(FacilityId::fromString(self::BUILDING), FacilityOrganizationId::fromString(self::ORG), FacilityType::BUILDING, new FacilityName('Building'));
  }

  /**
   * @param list<array{index: int, name: string}> $nodes
   */
  private function model(array $nodes = [['index' => 0, 'name' => 'shell']]): FacilityModel
  {
    return FacilityModel::create(self::MODEL, self::ORG, self::BUILDING, 'Building.glb', 100, $nodes);
  }
}

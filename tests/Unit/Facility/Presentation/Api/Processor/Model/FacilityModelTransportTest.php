<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Model;

use ApiPlatform\Metadata\{Delete, Get, GetCollection, Patch, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\Contract\Model\FacilityModelView;
use Facility\Application\UseCase\Command\Model\ActivateFacilityModel\{ActivateFacilityModelCommand, ActivateFacilityModelResult};
use Facility\Application\UseCase\Command\Model\DeleteFacilityModel\{DeleteFacilityModelCommand, DeleteFacilityModelResult};
use Facility\Application\UseCase\Command\Model\UpdateFacilityModel\{UpdateFacilityModelCommand, UpdateFacilityModelResult};
use Facility\Application\UseCase\Command\Model\UploadFacilityModel\{UploadFacilityModelCommand, UploadFacilityModelResult};
use Facility\Application\UseCase\Query\Model\GetFacilityModel\{GetFacilityModelQuery, GetFacilityModelResult};
use Facility\Application\UseCase\Query\Model\ListFacilityModels\{ListFacilityModelsQuery, ListFacilityModelsResult};
use Facility\Presentation\Api\Dto\Input\Model\UpdateFacilityModelInput;
use Facility\Presentation\Api\Dto\Output\Model\FacilityModelOutput;
use Facility\Presentation\Api\Operation\FacilityModelOperations;
use Facility\Presentation\Api\Processor\Model\FacilityModelProcessor;
use Facility\Presentation\Api\Provider\Model\FacilityModelProvider;
use Facility\Presentation\Api\Service\FacilityRevisionGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, PreconditionRequiredHttpException};
use Tests\Helper\GlbFixture;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test FacilityModelTransportTest. Transport mapping stays separate from handler authorization.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModelTransportTest extends TestCase
{
  #[Test]
  public function metadataAndCollectionProvidersDispatchTypedQueriesAndMapEveryField(): void
  {
    $view = $this->view();
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::exactly(2))->method('ask')->willReturnCallback(static function (mixed $query) use ($view): GetFacilityModelResult|ListFacilityModelsResult {
      if ($query instanceof GetFacilityModelQuery) {
        self::assertSame('model', $query->modelId);
        self::assertSame('member', $query->userId);

        return new GetFacilityModelResult($view);
      }
      self::assertInstanceOf(ListFacilityModelsQuery::class, $query);
      self::assertSame('org', $query->organizationId);
      self::assertSame('building', $query->buildingId);

      return new ListFacilityModelsResult([$view]);
    });
    $provider = new FacilityModelProvider($queries, $this->security());
    $output = $provider->provide(new Get(name: FacilityModelOperations::GET), ['id' => 'model']);
    self::assertInstanceOf(FacilityModelOutput::class, $output);
    self::assertSame('model', $output->id);
    self::assertSame(1, $output->nodeCount);
    self::assertSame('/api/facility-models/model/download', $output->downloadUrl);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $output->bindingIssues);
    $list = $provider->provide(new GetCollection(name: FacilityModelOperations::LIST), ['organizationId' => 'org', 'buildingId' => 'building']);
    self::assertIsArray($list);
    self::assertSame('model', $list[0]->id);
  }

  #[Test]
  public function settingsPatchCarriesTheExactExpectedRevisionAndPayload(): void
  {
    $view = $this->view();
    $input = new UpdateFacilityModelInput();
    $input->transform = $view->transform;
    $input->bindings = [];
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static function (mixed $command) use ($view): bool {
      self::assertInstanceOf(UpdateFacilityModelCommand::class, $command);
      self::assertSame(7, $command->expectedRevision);
      self::assertSame('member', $command->userId);
      self::assertSame($view->transform, $command->transform);
      self::assertSame([], $command->bindings);

      return true;
    }))->willReturn(new UpdateFacilityModelResult($view));
    $processor = $this->processor($commands, new Request(server: ['HTTP_IF_MATCH' => '"revision-7"']));
    self::assertSame('model', $processor->process($input, new Patch(name: FacilityModelOperations::UPDATE), ['id' => 'model'])?->id);
  }

  #[Test]
  public function transformOnlyPatchCarriesNullBindingsAndExplicitNodeRemoval(): void
  {
    $view = $this->view();
    $input = new UpdateFacilityModelInput();
    $input->transform = $view->transform;
    $input->removeBindingNodeIndices = [0];
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static function (mixed $command): bool {
      self::assertInstanceOf(UpdateFacilityModelCommand::class, $command);
      self::assertNull($command->bindings);
      self::assertSame([0], $command->removeBindingNodeIndices);

      return true;
    }))->willReturn(new UpdateFacilityModelResult($view));
    $this->processor($commands, new Request(server: ['HTTP_IF_MATCH' => '"revision-7"']))->process($input, new Patch(name: FacilityModelOperations::UPDATE), ['id' => 'model']);
  }

  #[Test]
  public function activationAndDeletionTranslateOnlyTheRequestedAction(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $view = $this->view();
    $commands->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (mixed $command) use ($view): ActivateFacilityModelResult|DeleteFacilityModelResult {
      if ($command instanceof ActivateFacilityModelCommand) {
        self::assertSame(7, $command->expectedRevision);

        return new ActivateFacilityModelResult($view);
      }
      self::assertInstanceOf(DeleteFacilityModelCommand::class, $command);
      self::assertSame(7, $command->expectedRevision);

      return new DeleteFacilityModelResult('model');
    });
    $processor = $this->processor($commands, new Request(server: ['HTTP_IF_MATCH' => '"revision-7"']));
    self::assertSame('model', $processor->process(null, new Post(name: FacilityModelOperations::ACTIVATE), ['id' => 'model'])?->id);
    self::assertNull($processor->process(null, new Delete(name: FacilityModelOperations::DELETE), ['id' => 'model']));
  }

  #[Test]
  public function multipartUploadCarriesFileContentsAndScopeIdentifiers(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-model-unit-');
    self::assertIsString($path);
    file_put_contents($path, GlbFixture::contents());

    try {
      $request = new Request(files: ['file' => new UploadedFile($path, 'Model.glb', 'model/gltf-binary', null, true)]);
      $view = $this->view();
      $commands = $this->createMock(CommandBusPort::class);
      $commands->expects(self::once())->method('dispatch')->with(self::callback(static function (mixed $command): bool {
        self::assertInstanceOf(UploadFacilityModelCommand::class, $command);
        self::assertSame('org', $command->organizationId);
        self::assertSame('building', $command->buildingId);
        self::assertSame(GlbFixture::contents(), $command->contents);

        return true;
      }))->willReturn(new UploadFacilityModelResult($view));
      $output = $this->processor($commands, $request)->process(null, new Post(name: FacilityModelOperations::UPLOAD), ['organizationId' => 'org', 'buildingId' => 'building']);
      self::assertSame('model', $output?->id);
      self::assertSame('/api/facility-models/model', $request->attributes->get('_api_write_item_iri'));
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function missingRevisionNeverDispatchesTheMutation(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(PreconditionRequiredHttpException::class);
    $this->processor($commands, new Request())->process(null, new Delete(name: FacilityModelOperations::DELETE), ['id' => 'model']);
  }

  #[Test]
  public function unauthenticatedProviderNeverQueriesTheBus(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new FacilityModelProvider($queries, $security)->provide(new Get(), ['id' => 'model']);
  }

  private function processor(CommandBusPort $commands, Request $request): FacilityModelProcessor
  {
    $requests = new RequestStack();
    $requests->push($request);

    return new FacilityModelProcessor($commands, $this->security(), $requests, new FacilityRevisionGuard($requests));
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('member', 'member@example.com', 'hashed', ['ROLE_USER']));

    return $security;
  }

  private function view(): FacilityModelView
  {
    return new FacilityModelView(
      'model',
      'org',
      'building',
      'Model.glb',
      300,
      [['index' => 0, 'name' => 'Shell']],
      7,
      false,
      ['scale' => 1.0, 'rotationDegrees' => 0.0, 'translation' => ['x' => 0.0, 'y' => 0.0, 'z' => 0.0]],
      [],
      new DateTimeImmutable('2026-10-03'),
      new DateTimeImmutable('2026-10-03'),
      [['nodeIndex' => 0, 'code' => 'target_unavailable']],
    );
  }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\{ListFacilityAttachmentsQuery, ListFacilityAttachmentsResult};
use Facility\Domain\Exception\FacilityAccessDeniedException;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/** Pins typed media reads, metadata mapping, and denial before secondary reads. */
#[CoversClass(FacilityMediaProvider::class)]
final class FacilityMediaProviderTest extends TestCase
{
  #[Test]
  public function collectionResolvesScopeThenForwardsKindAndMapsMetadata(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $call = 0;
    $queries->expects(self::exactly(2))->method('ask')->willReturnCallback(function (object $query) use (&$call): object {
      if (0 === $call++) {
        self::assertInstanceOf(GetFacilityMediaContextQuery::class, $query);
        self::assertSame('facility-id', $query->facilityId);
        self::assertSame('user-id', $query->userId);
        self::assertFalse($query->write);

        return new GetFacilityMediaContextResult('organization-id', 'facility-id');
      }
      self::assertInstanceOf(ListFacilityAttachmentsQuery::class, $query);
      self::assertSame('organization-id', $query->organizationId);
      self::assertSame('floor_plan', $query->kind);
      $view = $this->attachment();

      return new ListFacilityAttachmentsResult([[
        'id' => $view->id, 'fileName' => $view->fileName, 'mimeType' => $view->mimeType,
        'size' => $view->size, 'label' => $view->label, 'uploadedAt' => $view->uploadedAt,
        'kind' => $view->kind, 'isPrimaryPlan' => $view->isPrimaryPlan,
        'imageWidth' => $view->imageWidth, 'imageHeight' => $view->imageHeight,
        'calibration' => $view->calibration, 'calibrationBuildingId' => $view->calibrationBuildingId,
        'calibrationIssue' => $view->calibrationIssue, 'revision' => $view->revision,
      ]]);
    });
    $stack = new RequestStack();
    $stack->push(Request::create('/api/facilities/facility-id/attachments?kind=floor_plan'));
    $output = new FacilityMediaProvider($queries, $this->security(), $stack)->provide(new GetCollection(), ['facilityId' => 'facility-id']);
    self::assertIsArray($output);
    self::assertCount(1, $output);
    self::assertSame('attachment-id', $output[0]->id);
    self::assertSame('facility-id', $output[0]->facilityId);
    self::assertSame('building_changed', $output[0]->calibrationIssue);
    self::assertSame(6, $output[0]->revision);
  }

  #[Test]
  public function itemMapsTheAuthorizedSnapshotWithoutPersistence(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (object $query): bool => $query instanceof GetFacilityMediaContextQuery && 'attachment-id' === $query->attachmentId && !$query->write))->willReturn(new GetFacilityMediaContextResult('organization-id', 'facility-id', $this->attachment()));
    $output = new FacilityMediaProvider($queries, $this->security(), new RequestStack())->provide(new Get(), ['id' => 'attachment-id']);
    self::assertInstanceOf(\Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput::class, $output);
    self::assertSame('plan.png', $output->fileName);
    self::assertTrue($output->isPrimaryPlan);
    self::assertSame(['widthMeters' => 4.0, 'rotationDegrees' => 0.0, 'offsetXMeters' => 0.0, 'offsetZMeters' => 0.0], $output->calibration);
    self::assertSame('building_changed', $output->calibrationIssue);
    self::assertSame(6, $output->revision);
  }

  #[Test]
  public function missingAttachmentSnapshotReturns404(): void
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willReturn(new GetFacilityMediaContextResult('organization-id', 'facility-id'));
    $this->expectException(NotFoundHttpException::class);
    new FacilityMediaProvider($queries, $this->security(), new RequestStack())->provide(new Get(), ['id' => 'missing']);
  }

  #[Test]
  public function unauthenticatedItemDoesNotReachTheApplication(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    new FacilityMediaProvider($queries, $security, new RequestStack())->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function deniedScopeDoesNotReadTheCollection(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::isInstanceOf(GetFacilityMediaContextQuery::class))->willThrowException(new FacilityAccessDeniedException('Missing permission.'));
    $this->expectException(FacilityAccessDeniedException::class);
    new FacilityMediaProvider($queries, $this->security(), new RequestStack())->provide(new GetCollection(), ['facilityId' => 'facility-id']);
  }

  /**
   * @param array<string,mixed> $variables
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('invalidIdentifiers')]
  public function invalidIdentifiersFailBeforeTheQuery(array $variables, string $exception): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException($exception);
    new FacilityMediaProvider($queries, $this->security(), new RequestStack())->provide(new Get(), $variables);
  }

  /**
   * @return list<array{array<string,mixed>,class-string<Throwable>}>
   */
  public static function invalidIdentifiers(): array
  {
    return [[['facilityId' => ''], BadRequestHttpException::class], [['id' => 42], NotFoundHttpException::class], [[], NotFoundHttpException::class]];
  }

  private function attachment(): FacilityAttachmentView
  {
    return new FacilityAttachmentView('attachment-id', 'facility-id', 'plan.png', 'image/png', 2048, 'Plan', '2026-01-01T00:00:00+00:00', 'floor_plan', true, 800, 600, ['widthMeters' => 4.0, 'rotationDegrees' => 0.0, 'offsetXMeters' => 0.0, 'offsetZMeters' => 0.0], 'building-id', 'building_changed', 6);
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true));

    return $security;
  }
}

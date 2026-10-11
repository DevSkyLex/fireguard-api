<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentResult};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Facility\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Facility\Presentation\Api\Http\RevisionGuard;
use Facility\Presentation\Api\Processor\Attachment\FacilityMediaProcessor;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, PreconditionFailedHttpException, PreconditionRequiredHttpException, UnprocessableEntityHttpException};
use Throwable;

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** Pins media transport validation, typed dispatch, and ordered access/revision gates. */
#[CoversClass(FacilityMediaProcessor::class)]
final class FacilityMediaProcessorTest extends TestCase
{
  #[Test]
  #[DataProvider('uploadKinds')]
  public function uploadDispatchesOneCommandAndReadsPersistedMetadata(string $kind): void
  {
    $path = $this->png();

    try {
      $stack = $this->stack('POST');
      $this->request($stack)->files->set('file', new UploadedFile($path, 'plan.png', 'image/png', null, true));
      $this->request($stack)->request->set('kind', $kind);
      $queries = $this->createMock(QueryBusPort::class);
      $calls = 0;
      $queries->expects(self::exactly(2))->method('ask')->willReturnCallback(function (object $query) use (&$calls): object {
        self::assertInstanceOf(GetFacilityMediaContextQuery::class, $query);
        self::assertTrue($query->write);
        self::assertSame('user-id', $query->userId);
        if (0 === $calls++) {
          self::assertSame('facility-id', $query->facilityId);

          return new GetFacilityMediaContextResult('organization-id', 'facility-id');
        }
        self::assertSame('attachment-id', $query->attachmentId);

        return new GetFacilityMediaContextResult('organization-id', 'facility-id', $this->attachment());
      });
      $commands = $this->createMock(CommandBusPort::class);
      $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $command): bool => $command instanceof AddFacilityAttachmentCommand && 'organization-id' === $command->organizationId && 'facility-id' === $command->facilityId && 'user-id' === $command->userId && $kind === $command->kind && 'image/png' === $command->mimeType && '' !== $command->contents))->willReturn(new AddFacilityAttachmentResult('attachment-id', 'facility-id', 'plan.png', 'image/png', 68, null, new DateTimeImmutable()));
      $output = $this->processor($commands, $queries, $stack)->process(null, new Post(), ['facilityId' => 'facility-id']);
      self::assertInstanceOf(\Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput::class, $output);
      self::assertSame('attachment-id', $output->id);
      self::assertSame(9, $output->revision);
    } finally {
      unlink($path);
    }
  }

  /**
   * @return list<array{string}>
   */
  public static function uploadKinds(): array
  {
    return [['document'], ['floor_plan']];
  }

  #[Test]
  public function deleteCarriesTheRevisionFromTheAuthorizedSnapshot(): void
  {
    $queries = $this->queries($this->attachment());
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $command): bool => $command instanceof DeleteFacilityAttachmentCommand && 9 === $command->expectedRevision && 'attachment-id' === $command->attachmentId && 'user-id' === $command->userId))->willReturn($this->createStub(ResultMessage::class));
    self::assertNull($this->processor($commands, $queries, $this->stack('DELETE', '"revision-9"'))->process(null, new Delete(), ['id' => 'attachment-id']));
  }

  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('invalidRevisions')]
  public function deleteRevisionRefusalNeverDispatches(?string $ifMatch, string $exception): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException($exception);
    $this->processor($commands, $this->queries($this->attachment()), $this->stack('DELETE', $ifMatch))->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  /**
   * @return list<array{?string, class-string<Throwable>}>
   */
  public static function invalidRevisions(): array
  {
    return [[null, PreconditionRequiredHttpException::class], ['"revision-8"', PreconditionFailedHttpException::class]];
  }

  #[Test]
  public function scopeDenialPrecedesMultipartValidationAndNeverDispatches(): void
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willThrowException(new FacilityAccessDeniedException('Missing permission.'));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(FacilityAccessDeniedException::class);
    $this->processor($commands, $queries, $this->stack('POST'))->process(null, new Post(), ['facilityId' => 'facility-id']);
  }

  #[Test]
  public function missingOwnerPrecedesDeleteRevisionValidation(): void
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willThrowException(new FacilityNotFoundException('Facility not found.'));
    $this->expectException(FacilityNotFoundException::class);
    $this->processor($this->createStub(CommandBusPort::class), $queries, $this->stack('DELETE'))->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function missingAttachmentSnapshotReturns404BeforeRevisionValidation(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->processor($this->createStub(CommandBusPort::class), $this->queries(), $this->stack('DELETE'))->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function missingUploadedSnapshotReturns404(): void
  {
    $path = $this->png();

    try {
      $stack = $this->stack('POST');
      $this->request($stack)->files->set('file', new UploadedFile($path, 'plan.png', 'image/png', null, true));
      $commands = $this->createStub(CommandBusPort::class);
      $commands->method('dispatch')->willReturn(new AddFacilityAttachmentResult('attachment-id', 'facility-id', 'plan.png', 'image/png', 68, null, new DateTimeImmutable()));
      $this->expectException(NotFoundHttpException::class);
      $this->processor($commands, $this->queries(), $stack)->process(null, new Post(), ['facilityId' => 'facility-id']);
    } finally {
      unlink($path);
    }
  }

  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('invalidUploads')]
  public function invalidUploadFailsBeforeDispatch(string $kind, bool $file, string $exception): void
  {
    $stack = $this->stack('POST');
    $this->request($stack)->request->set('kind', $kind);
    $path = null;
    if ($file) {
      $path = tempnam(sys_get_temp_dir(), 'facility-invalid-');
      file_put_contents($path, 'untrusted plain text');
      $this->request($stack)->files->set('file', new UploadedFile($path, 'plan.png', 'image/png', null, true));
    }

    try {
      $commands = $this->createMock(CommandBusPort::class);
      $commands->expects(self::never())->method('dispatch');
      $this->expectException($exception);
      $this->processor($commands, $this->queries(), $stack)->process(null, new Post(), ['facilityId' => 'facility-id']);
    } finally {
      if (null !== $path) {
        unlink($path);
      }
    }
  }

  /**
   * @return list<array{string, bool, class-string<Throwable>}>
   */
  public static function invalidUploads(): array
  {
    return [['unknown', false, BadRequestHttpException::class], ['document', false, BadRequestHttpException::class], ['floor_plan', true, UnprocessableEntityHttpException::class]];
  }

  #[Test]
  public function unauthenticatedUploadNeverQueriesTheApplication(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $this->expectException(AccessDeniedHttpException::class);
    $this->processor($this->createStub(CommandBusPort::class), $queries, $this->stack('POST'), $security)->process(null, new Post(), ['facilityId' => 'facility-id']);
  }

  #[Test]
  public function oversizedFloorPlanIsRejectedBeforeReadingContents(): void
  {
    $path = $this->png();

    try {
      $stack = $this->stack('POST');
      $this->request($stack)->request->set('kind', 'floor_plan');
      $file = new class ($path, 'plan.png', 'image/png', null, true) extends UploadedFile {
        public function getSize(): int
        {
          return \Shared\Domain\Attachment\AttachmentConstraints::MAX_SIZE_BYTES + 1;
        }

        public function getContent(): string
        {
          throw new LogicException('Oversized upload contents must not be read.');
        }
      };
      $this->request($stack)->files->set('file', $file);
      $commands = $this->createMock(CommandBusPort::class);
      $commands->expects(self::never())->method('dispatch');
      $this->expectException(UnprocessableEntityHttpException::class);
      $this->processor($commands, $this->queries(), $stack)->process(null, new Post(), ['facilityId' => 'facility-id']);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function missingRequestDoesNotDispatch(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    $this->processor($commands, $this->queries(), new RequestStack())->process(null, new Post(), ['facilityId' => 'facility-id']);
  }

  /**
   * @param class-string<Throwable> $exception
   * @param array<string,mixed> $variables
   */
  #[Test]
  #[DataProvider('invalidRouteIdentifiers')]
  public function invalidRouteIdentifiersNeverQuery(string $method, array $variables, string $exception): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException($exception);
    $this->processor($commands, $queries, $this->stack($method))->process(null, new Post(), $variables);
  }

  /**
   * @return list<array{string, array<string, mixed>, class-string<Throwable>}>
   */
  public static function invalidRouteIdentifiers(): array
  {
    return [['POST', [], BadRequestHttpException::class], ['POST', ['facilityId' => ''], BadRequestHttpException::class], ['DELETE', ['id' => 42], NotFoundHttpException::class]];
  }

  private function attachment(): FacilityAttachmentView
  {
    return new FacilityAttachmentView('attachment-id', 'facility-id', 'plan.png', 'image/png', 68, null, 'now', 'floor_plan', true, 1, 1, null, null, null, 9);
  }

  private function queries(?FacilityAttachmentView $attachment = null): QueryBusPort
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willReturn(new GetFacilityMediaContextResult('organization-id', 'facility-id', $attachment));

    return $queries;
  }

  private function request(RequestStack $stack): Request
  {
    $request = $stack->getCurrentRequest();
    self::assertNotNull($request);

    return $request;
  }

  private function stack(string $method, ?string $ifMatch = null): RequestStack
  {
    $stack = new RequestStack();
    $request = Request::create('/api/media', $method);
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }
    $stack->push($request);

    return $stack;
  }

  private function processor(CommandBusPort $commands, QueryBusPort $queries, RequestStack $stack, ?Security $security = null): FacilityMediaProcessor
  {
    if (null === $security) {
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true));
    }

    return new FacilityMediaProcessor($commands, $security, $stack, new MultipartAttachmentGuard(), new RevisionGuard($stack), $queries);
  }

  private function png(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'facility-png-');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1cAAAAASUVORK5CYII=', true));

    return $path;
  }
}

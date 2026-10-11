<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentResult};
use Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentCommand;
use Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\{ResolveInspectionMediaQuery, ResolveInspectionMediaResult};
use Inspection\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Inspection\Presentation\Api\Http\RevisionGuard;
use Inspection\Presentation\Api\Processor\Attachment\InspectionMediaProcessor;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{PreconditionFailedHttpException, PreconditionRequiredHttpException};
use Throwable;

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class InspectionMediaProcessorTest extends TestCase
{
  private const string CLIENT = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function forwardsTheActorAndMultipartSnapshotToOneCommand(): void
  {
    $file = tempnam(sys_get_temp_dir(), 'media-');
    self::assertIsString($file);
    file_put_contents($file, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true));

    try {
      $request = Request::create('/attachments', 'POST', ['label' => 'proof'], [], ['file' => new UploadedFile($file, 'proof.gif', 'image/gif', null, true)]);
      $commands = $this->createMock(CommandBusPort::class);
      $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (AddInspectionAttachmentCommand $command): bool => 'actor' === $command->userId && 'organization' === $command->organizationId && 'inspection' === $command->inspectionId && 'proof' === $command->label && 'proof.gif' === $command->fileName))->willReturn(new AddInspectionAttachmentResult(
        attachmentId: self::CLIENT,
        inspectionId: 'inspection',
        fileName: 'proof.gif',
        mimeType: 'image/gif',
        size: 43,
        label: 'proof',
        uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        nonConformityId: null,
      ));
      $queries = $this->createMock(QueryBusPort::class);
      $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ResolveInspectionMediaQuery $query): bool => 'actor' === $query->userId && $query->write && 'inspection' === $query->inspectionId))->willReturn(new ResolveInspectionMediaResult('organization', 'inspection'));
      $output = $this->processor($request, $commands, $queries)->process(null, new Post(), ['inspectionId' => 'inspection']);
      self::assertInstanceOf(\Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput::class, $output);
      self::assertSame(self::CLIENT, $output->id);
      self::assertSame('proof', $output->label);
    } finally {
      unlink($file);
    }
  }

  /**
   * @return iterable<string, array{?string, class-string<Throwable>}>
   */
  public static function staleHeaders(): iterable
  {
    yield 'missing' => [null, PreconditionRequiredHttpException::class];
    yield 'stale' => ['"revision-2"', PreconditionFailedHttpException::class];
  }

  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('staleHeaders')]
  public function refusesDeletionBeforeDispatchWhenTheHeaderFails(?string $header, string $exception): void
  {
    $request = Request::create('/attachments', 'DELETE');
    if (null !== $header) {
      $request->headers->set('If-Match', $header);
    }
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $queries = $this->scope();
    $this->expectException($exception);
    $this->processor($request, $commands, $queries)->process(null, new Delete(), ['id' => self::CLIENT]);
  }

  #[Test]
  public function forwardsTheComparedRevisionToTheOwningDeleteTransaction(): void
  {
    $request = Request::create('/attachments', 'DELETE');
    $request->headers->set('If-Match', '"revision-3"');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (DeleteInspectionAttachmentCommand $command): bool => 3 === $command->expectedRevision && self::CLIENT === $command->attachmentId));
    self::assertNull($this->processor($request, $commands, $this->scope())->process(null, new Delete(), ['id' => self::CLIENT]));
  }

  #[Test]
  public function resolvesTheScopedNonConformityBeforeMultipartValidation(): void
  {
    $request = Request::create('/attachments', 'POST');
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ResolveInspectionMediaQuery $query): bool => $query->write && 'finding' === $query->nonConformityId))->willThrowException(new \Inspection\Domain\Exception\InspectionAccessDeniedException('Missing organization.inspection.write permission.'));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(\Inspection\Domain\Exception\InspectionAccessDeniedException::class);
    $this->processor($request, $commands, $queries)->process(null, new Post(), ['nonConformityId' => 'finding']);
  }

  private function scopeResult(): ResolveInspectionMediaResult
  {
    return new ResolveInspectionMediaResult('organization', 'inspection', new InspectionAttachmentView(self::CLIENT, 'organization', 'inspection', 'file.pdf', 'application/pdf', 4, null, 3, '2026-01-01T00:00:00+00:00', null));
  }

  private function scope(): QueryBusPort
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willReturn($this->scopeResult());

    return $queries;
  }

  private function processor(Request $request, CommandBusPort $commands, QueryBusPort $queries): InspectionMediaProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('actor', 'actor@example.com', '', [], [], true));
    $stack = new RequestStack();
    $stack->push($request);

    return new InspectionMediaProcessor($commands, $queries, $security, $stack, new MultipartAttachmentGuard(), new RevisionGuard($stack));
  }
}

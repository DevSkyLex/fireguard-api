<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentResult};
use Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment\DeleteInterventionAttachmentCommand;
use Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia\{ResolveInterventionMediaQuery, ResolveInterventionMediaResult};
use Intervention\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Intervention\Presentation\Api\Http\RevisionGuard;
use Intervention\Presentation\Api\Processor\Attachment\InterventionMediaProcessor;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, PreconditionFailedHttpException, PreconditionRequiredHttpException};
use Throwable;

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class InterventionMediaProcessorTest extends TestCase
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
      $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (AddInterventionAttachmentCommand $command): bool => 'actor' === $command->userId && 'intervention' === $command->interventionId && 'proof' === $command->label && 'proof.gif' === $command->fileName))->willReturn(new AddInterventionAttachmentResult(
        attachmentId: self::CLIENT,
        interventionId: 'intervention',
        fileName: 'proof.gif',
        mimeType: 'image/gif',
        size: 43,
        label: 'proof',
        uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      ));
      $queries = $this->createMock(QueryBusPort::class);
      $queries->expects(self::never())->method('ask');
      $output = $this->processor($request, $commands, $queries)->process(null, new Post(), ['interventionId' => 'intervention']);
      self::assertInstanceOf(\Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput::class, $output);
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
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (DeleteInterventionAttachmentCommand $command): bool => 3 === $command->expectedRevision && self::CLIENT === $command->attachmentId));
    self::assertNull($this->processor($request, $commands, $this->scope())->process(null, new Delete(), ['id' => self::CLIENT]));
  }

  #[Test]
  public function replaysTheScopedSnapshotWithoutAFileOrMutation(): void
  {
    $request = Request::create('/attachments', 'POST', ['clientId' => self::CLIENT]);
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ResolveInterventionMediaQuery $query): bool => $query->replay && self::CLIENT === $query->attachmentId))->willReturn($this->scopeResult());
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $output = $this->processor($request, $commands, $queries)->process(null, new Post(), ['interventionId' => 'intervention']);
    self::assertInstanceOf(\Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput::class, $output);
    self::assertSame(3, $output->revision);
  }

  #[Test]
  public function validatesAClientUuidBeforeReadingMultipartBytes(): void
  {
    $request = Request::create('/attachments', 'POST', ['clientId' => 'invalid']);
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(BadRequestHttpException::class);
    $this->processor($request, $commands, $queries)->process(null, new Post(), ['interventionId' => 'intervention']);
  }

  private function scopeResult(): ResolveInterventionMediaResult
  {
    return new ResolveInterventionMediaResult('organization', 'intervention', new InterventionAttachmentView(self::CLIENT, 'organization', 'intervention', 'file.pdf', 'application/pdf', 4, null, 3, '2026-01-01T00:00:00+00:00', null, 'file'));
  }

  private function scope(): QueryBusPort
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willReturn($this->scopeResult());

    return $queries;
  }

  private function processor(Request $request, CommandBusPort $commands, QueryBusPort $queries): InterventionMediaProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('actor', 'actor@example.com', '', [], [], true));
    $stack = new RequestStack();
    $stack->push($request);

    return new InterventionMediaProcessor($commands, $queries, $security, $stack, new MultipartAttachmentGuard(), new RevisionGuard($stack));
  }
}

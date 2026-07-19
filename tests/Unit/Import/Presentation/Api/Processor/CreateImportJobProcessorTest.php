<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Import\Application\UseCase\Command\CreateImportJob\{CreateImportJobCommand, CreateImportJobResult};
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Import\Presentation\Api\Processor\CreateImportJobProcessor;
use Import\Presentation\Api\Service\CsvUploadGuard;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test CreateImportJobProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateImportJobProcessor::class)]
final class CreateImportJobProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f6aa01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testUploadDispatchesTheCommandAndReturns202Output(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'import-');
    self::assertIsString($path);
    file_put_contents($path, "type\nfire_extinguisher\n");

    try {
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );

      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/imports',
        'POST',
        ['organization' => '/api/organizations/' . self::ORGANIZATION_ID, 'kind' => 'equipment'],
        [],
        ['file' => new UploadedFile($path, 'equipment.csv', 'text/csv', null, true)],
      ));

      $captured = null;
      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::once())
        ->method('dispatch')
        ->willReturnCallback(function (CreateImportJobCommand $command) use (&$captured): CreateImportJobResult {
          $captured = $command;

          return new CreateImportJobResult(
            importJobId: 'job-1',
            organizationId: self::ORGANIZATION_ID,
            kind: 'equipment',
            status: 'pending',
            originalFilename: 'equipment.csv',
            createdAt: new DateTimeImmutable('2026-01-05T00:00:00+00:00'),
          );
        });

      $processor = new CreateImportJobProcessor(
        $commandBus,
        $security,
        $requestStack,
        new CsvUploadGuard(),
        new ImportJobOutputFactory(),
      );

      $output = $processor->process(null, new Post());

      self::assertInstanceOf(CreateImportJobCommand::class, $captured);
      self::assertSame(self::USER_ID, $captured->userId);
      self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
      self::assertSame('equipment', $captured->kind);
      self::assertSame('equipment.csv', $captured->fileName);
      self::assertStringContainsString('fire_extinguisher', $captured->contents);

      self::assertInstanceOf(ImportJobOutput::class, $output);
      self::assertSame('job-1', $output->id);
      self::assertSame('pending', $output->status);
      self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/imports', 'POST'));

    $processor = new CreateImportJobProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      $requestStack,
      new CsvUploadGuard(),
      new ImportJobOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post());
  }
}

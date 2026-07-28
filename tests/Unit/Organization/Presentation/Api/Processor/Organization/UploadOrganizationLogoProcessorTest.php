<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{
  UpdateOrganizationSettingsCommand,
  UpdateOrganizationSettingsResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Infrastructure\Image\OrganizationLogoResizer;
use Organization\Presentation\Api\Processor\Organization\UploadOrganizationLogoProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\FileStoragePort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};

use function file_put_contents;
use function imagecreatetruecolor;
use function imagepng;
use function is_string;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const UPLOAD_ERR_PARTIAL;

/**
 * Test UploadOrganizationLogoProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UploadOrganizationLogoProcessor::class)]
final class UploadOrganizationLogoProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';
  // #endregion

  // #region Properties
  /**
   * @var list<string>
   */
  private array $temporaryFiles = [];
  // #endregion

  // #region Methods
  protected function tearDown(): void
  {
    foreach ($this->temporaryFiles as $path) {
      @unlink($path);
    }

    $this->temporaryFiles = [];

    parent::tearDown();
  }

  #[Test]
  public function testProcessResizesTheLogoAndPersistsItsUrl(): void
  {
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::atLeastOnce())->method('write');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (UpdateOrganizationSettingsCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && is_string($command->logoUrl)
        && str_contains($command->logoUrl, '/api/organizations/' . self::ORGANIZATION_ID . '/logo.webp?v=')))
      ->willReturn(new UpdateOrganizationSettingsResult(self::ORGANIZATION_ID));

    $output = $this->createProcessor(
      request: $this->requestWithLogo(),
      commandBus: $commandBus,
      fileStorage: $fileStorage,
    )->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertSame(self::ORGANIZATION_ID, $output->id);
    self::assertSame('Fireguard Test', $output->name);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = $this->createProcessor(security: $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenTheOrganizationIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessThrowsWhenThePermissionIsMissing(): void
  {
    $processor = $this->createProcessor(authorizationGranted: false);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.settings.write permission.');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenThereIsNoActiveRequest(): void
  {
    $processor = $this->createProcessor(request: null);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('No active request.');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenTheLogoFileIsMissing(): void
  {
    $processor = $this->createProcessor(request: new Request());

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Missing "logo" file in the request.');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRejectsAnUploadThatFailedTransport(): void
  {
    $request = new Request();
    $request->files->set('logo', new UploadedFile(
      $this->temporaryFile('partial'),
      'logo.png',
      'image/png',
      UPLOAD_ERR_PARTIAL,
      test: true,
    ));

    $processor = $this->createProcessor(request: $request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Invalid upload:');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRejectsAFileLargerThanTheMaximumSize(): void
  {
    $request = new Request();
    $request->files->set('logo', new UploadedFile(
      $this->temporaryFile(str_repeat('a', 5 * 1024 * 1024 + 1)),
      'logo.png',
      'image/png',
      test: true,
    ));

    $processor = $this->createProcessor(request: $request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('File size exceeds the 5 MB limit.');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRejectsADisallowedMimeType(): void
  {
    $path = $this->temporaryFile('not-an-image');
    $request = new Request();
    $request->files->set('logo', new UploadedFile($path, 'logo.txt', 'text/plain', test: true));

    $processor = $this->createProcessor(request: $request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Invalid MIME type');

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessMapsAMissingOrganizationOnTheReadBackToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      OrganizationNotFoundException::withId(self::ORGANIZATION_ID),
    );

    $processor = $this->createProcessor(request: $this->requestWithLogo(), queryBus: $queryBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenTheUploadedBytesCannotBeRead(): void
  {
    // The upload passes every earlier gate but its bytes are gone by the time
    // the processor reads them — a real race when a tmp file is reaped early.
    $uploadedFile = new class ($this->pngFile(), 'logo.png', 'image/png', test: true) extends UploadedFile {
      public function getPathname(): string
      {
        return sys_get_temp_dir() . '/fireguard-logo-vanished-source.png';
      }

      public function getMimeType(): string
      {
        return 'image/png';
      }
    };

    $request = new Request();
    $request->files->set('logo', $uploadedFile);

    $processor = $this->createProcessor(request: $request);

    // file_get_contents() emits an E_WARNING for the missing path; swallow it
    // locally so the suite's failOnWarning gate still guards everything else.
    set_error_handler(static fn (): bool => true);

    try {
      $processor->process(null, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
      self::fail('Expected an UnprocessableEntityHttpException.');
    } catch (UnprocessableEntityHttpException $exception) {
      self::assertSame('Failed to read the uploaded file.', $exception->getMessage());
    } finally {
      restore_error_handler();
    }
  }

  private function createProcessor(
    ?Request $request = null,
    ?CommandBusPort $commandBus = null,
    ?QueryBusPort $queryBus = null,
    ?FileStoragePort $fileStorage = null,
    ?Security $security = null,
    bool $authorizationGranted = true,
  ): UploadOrganizationLogoProcessor {
    $requestStack = new RequestStack();
    if (null !== $request) {
      $requestStack->push($request);
    }

    if (null === $queryBus) {
      $queryBus = $this->createStub(QueryBusPort::class);
      $queryBus->method('ask')->willReturn($this->organizationResult());
    }

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($authorizationGranted);

    return new UploadOrganizationLogoProcessor(
      requestStack: $requestStack,
      logoResizer: new OrganizationLogoResizer(
        $fileStorage ?? $this->createStub(FileStoragePort::class),
      ),
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security ?? $this->securityWithUser(),
    );
  }

  private function requestWithLogo(): Request
  {
    $request = new Request();
    $request->files->set('logo', new UploadedFile(
      $this->pngFile(),
      'logo.png',
      'image/png',
      test: true,
    ));

    return $request;
  }

  private function pngFile(): string
  {
    $image = imagecreatetruecolor(64, 64);
    self::assertNotFalse($image);

    $path = $this->temporaryFile('');
    imagepng($image, $path);

    return $path;
  }

  private function temporaryFile(string $contents): string
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-logo-');
    self::assertNotFalse($path);

    file_put_contents($path, $contents);
    $this->temporaryFiles[] = $path;

    return $path;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function organizationResult(): GetOrganizationResult
  {
    return new GetOrganizationResult(
      id: self::ORGANIZATION_ID,
      name: 'Fireguard Test',
      slug: 'fireguard-test',
      ownerUserId: self::USER_ID,
      createdByUserId: self::USER_ID,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}

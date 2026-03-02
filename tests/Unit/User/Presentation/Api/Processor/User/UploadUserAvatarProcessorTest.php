<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Post;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Component\HttpFoundation\{File\UploadedFile, Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Infrastructure\Image\AvatarResizer;
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Processor\User\UploadUserAvatarProcessor;

use function assert;
use function base64_decode;
use function file_put_contents;
use function is_string;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const UPLOAD_ERR_OK;

/**
 * Test UploadUserAvatarProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UploadUserAvatarProcessor::class)]
final class UploadUserAvatarProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessReturnsNullWhenIdMissing(): void
  {
    $processor = $this->makeProcessor();

    $result = $processor->process(null, new Post(), []);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessReturnsNullWhenNoRequest(): void
  {
    $requestStack = new RequestStack();

    $processor = new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $this->createMock(AvatarResizer::class),
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $result = $processor->process(null, new Post(), ['id' => 'user-1']);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessThrowsWhenNoFileAttached(): void
  {
    $request = new Request();
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $this->createMock(AvatarResizer::class),
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Missing "avatar" file in the request.');

    $processor->process(null, new Post(), ['id' => 'user-1']);
  }

  #[Test]
  public function testProcessThrowsWhenMimeTypeInvalid(): void
  {
    $tmpFile = $this->createTempFile('fake pdf content', 'application/pdf');

    $uploadedFile = new UploadedFile(
      path: $tmpFile,
      originalName: 'document.pdf',
      mimeType: 'application/pdf',
      error: UPLOAD_ERR_OK,
      test: true,
    );

    $request = new Request();
    $request->files->set('avatar', $uploadedFile);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $this->createMock(AvatarResizer::class),
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Invalid MIME type');

    try {
      $processor->process(null, new Post(), ['id' => 'user-1']);
    } finally {
      @unlink($tmpFile);
    }
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $tmpFile = $this->createTempFile($this->minimalPngBinary(), 'image/png');

    $uploadedFile = new UploadedFile(
      path: $tmpFile,
      originalName: 'avatar.png',
      mimeType: 'image/png',
      error: UPLOAD_ERR_OK,
      test: true,
    );

    $request = Request::create('https://api.example.com/api/users/user-1/avatar', 'POST');
    $request->files->set('avatar', $uploadedFile);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $userView = $this->makeUserView('user-1', 'https://api.example.com/api/users/user-1/avatar');

    /** @var AvatarResizer&MockObject $resizer */
    $resizer = $this->createMock(AvatarResizer::class);
    $resizer->expects(self::once())->method('delete')->with('user-1');
    $resizer->expects(self::once())->method('resize')->with('user-1', self::isType('string'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (UpdateUserCommand $cmd) => 'user-1' === $cmd->id
          && null !== $cmd->avatarUrl
          && str_contains($cmd->avatarUrl, '/api/users/user-1/avatar'),
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    $processor = new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $resizer,
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    try {
      $output = $processor->process(null, new Post(), ['id' => 'user-1']);
    } finally {
      @unlink($tmpFile);
    }

    self::assertInstanceOf(UserOutput::class, $output);
    self::assertSame('user-1', $output->id);
    self::assertSame('https://api.example.com/api/users/user-1/avatar', $output->avatarUrl);
  }

  #[Test]
  public function testProcessReturnsNullWhenQueryReturnsNoUser(): void
  {
    $tmpFile = $this->createTempFile($this->minimalPngBinary(), 'image/png');

    $uploadedFile = new UploadedFile(
      path: $tmpFile,
      originalName: 'avatar.png',
      mimeType: 'image/png',
      error: UPLOAD_ERR_OK,
      test: true,
    );

    $request = Request::create('https://api.example.com/', 'POST');
    $request->files->set('avatar', $uploadedFile);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult(user: null));

    /** @var AvatarResizer&MockObject $resizer */
    $resizer = $this->createMock(AvatarResizer::class);
    $resizer->method('delete');
    $resizer->method('resize');

    $processor = new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $resizer,
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $queryBus,
    );

    try {
      $output = $processor->process(null, new Post(), ['id' => 'user-1']);
    } finally {
      @unlink($tmpFile);
    }

    self::assertNull($output);
  }

  // #region Helpers
  private function makeProcessor(): UploadUserAvatarProcessor
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    return new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $this->createMock(AvatarResizer::class),
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );
  }

  private function makeUserView(string $id, ?string $avatarUrl = null): UserView
  {
    return new UserView(
      id: $id,
      username: 'jdoe',
      email: 'jdoe@example.com',
      firstName: 'John',
      lastName: 'Doe',
      avatarUrl: $avatarUrl,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
    );
  }

  private function createTempFile(string $contents, string $mimeType): string
  {
    $path = tempnam(sys_get_temp_dir(), 'avatar_test_');
    file_put_contents($path, $contents);

    return $path;
  }

  private function minimalPngBinary(): string
  {
    $binary = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      true,
    );
    assert(is_string($binary));

    return $binary;
  }
  // #endregion
}

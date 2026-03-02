<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Controller\User;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\ValueObject\Email;
use Shared\Infrastructure\Exception\FileStorageException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Presentation\Api\Controller\User\GetUserAvatarController;

use function sprintf;

/**
 * Test GetUserAvatarControllerTest.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetUserAvatarController::class)]
final class GetUserAvatarControllerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  // #region Methods
  #[Test]
  public function testInvokeThrows404WhenUserNotFound(): void
  {
    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn(null);

    $controller = new GetUserAvatarController(
      fileStorage: $this->createMock(FileStoragePort::class),
      userRepository: $repo,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('User not found.');

    $controller->__invoke(self::USER_ID, new Request());
  }

  #[Test]
  public function testInvokeThrows404WhenAvatarFileMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn($this->makeUser(self::USER_ID));

    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);
    $storage->method('read')->willThrowException(
      FileStorageException::readFailed(sprintf('avatars/%s/256.webp', self::USER_ID)),
    );

    $controller = new GetUserAvatarController(
      fileStorage: $storage,
      userRepository: $repo,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Avatar not found.');

    $controller->__invoke(self::USER_ID, new Request());
  }

  #[Test]
  public function testInvokeReturnsWebpResponseWithDefaultSize(): void
  {
    $fakeWebp = 'fake-webp-contents';

    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn($this->makeUser(self::USER_ID));

    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())
      ->method('read')
      ->with(sprintf('avatars/%s/256.webp', self::USER_ID))
      ->willReturn($fakeWebp);

    $controller = new GetUserAvatarController(
      fileStorage: $storage,
      userRepository: $repo,
    );

    $response = $controller->__invoke(self::USER_ID, new Request());

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('image/webp', $response->headers->get('Content-Type'));
    $cacheControl = $response->headers->get('Cache-Control') ?? '';
    self::assertStringContainsString('public', $cacheControl);
    self::assertStringContainsString('max-age=86400', $cacheControl);
    self::assertSame($fakeWebp, $response->getContent());
  }

  #[Test]
  #[DataProvider('sizeResolutionProvider')]
  public function testSizeQueryParamResolvesToNearestVariant(int $requested, int $expectedSize): void
  {
    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn($this->makeUser(self::USER_ID));

    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())
      ->method('read')
      ->with(sprintf('avatars/%s/%d.webp', self::USER_ID, $expectedSize))
      ->willReturn('data');

    $controller = new GetUserAvatarController(
      fileStorage: $storage,
      userRepository: $repo,
    );

    $request = new Request(['size' => (string) $requested]);
    $controller->__invoke(self::USER_ID, $request);
  }

  /**
   * @return iterable<string, array{int, int}>
   */
  public static function sizeResolutionProvider(): iterable
  {
    yield 'exact 256' => [256, 256];
    yield 'exact 128' => [128, 128];
    yield 'exact 64' => [64, 64];
    yield 'exact 32' => [32, 32];
    yield 'below 32' => [10, 32];
    yield 'above 256' => [512, 256];
    yield 'midpoint 192' => [192, 256];
    yield 'midpoint 96' => [96, 128];
    yield 'near 64' => [80, 64];
    yield 'near 32' => [40, 32];
  }
  // #endregion

  // #region Helpers
  private function makeUser(string $id): User
  {
    return User::register(
      id: new UserId($id),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}

<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\GetCurrentUserProfileResult;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;
use User\Domain\Exception\UserNotFoundException;
use User\Infrastructure\Image\AvatarResizer;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Processor\User\{
  UploadCurrentUserAvatarProcessor,
  UploadUserAvatarProcessor
};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

#[CoversClass(UploadCurrentUserAvatarProcessor::class)]
final class UploadCurrentUserAvatarProcessorTest extends TestCase
{
  /**
   * A 1x1 transparent PNG, small enough to inline and real enough that the
   * delegate's MIME sniffing accepts it.
   */
  private const string PNG_1X1_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $processor = new UploadCurrentUserAvatarProcessor(
      security: $this->createStub(Security::class),
      uploadUserAvatarProcessor: $this->createUploadUserAvatarProcessor(),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Put());
  }

  #[Test]
  public function testProcessDelegatesForAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')
      ->willReturn(new SecurityUser(
        id: '550e8400-e29b-41d4-a716-446655442101',
        email: 'user@example.com',
        password: 'hashed-password',
      ));

    $processor = new UploadCurrentUserAvatarProcessor(
      security: $security,
      uploadUserAvatarProcessor: $this->createUploadUserAvatarProcessor(),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    self::assertNull($processor->process(null, new Put()));
  }

  #[Test]
  public function testProcessMapsAMissingProfileToNotFound(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442102';

    $security = $this->createStub(Security::class);
    $security->method('getUser')
      ->willReturn(new SecurityUser(
        id: $userId,
        email: 'user@example.com',
        password: 'hashed-password',
      ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(UserNotFoundException::withId($userId));

    $processor = new UploadCurrentUserAvatarProcessor(
      security: $security,
      uploadUserAvatarProcessor: $this->createUploadingProcessor($userId),
      queryBus: $queryBus,
    );

    $this->expectException(UserNotFoundException::class);

    $processor->process(null, new Put());
  }

  #[Test]
  public function testProcessReturnsTheRefreshedCurrentUserProfile(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442103';

    $security = $this->createStub(Security::class);
    $security->method('getUser')
      ->willReturn(new SecurityUser(
        id: $userId,
        email: 'user@example.com',
        password: 'hashed-password',
      ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetCurrentUserProfileResult(
      user: $this->makeUserView($userId),
      roles: ['ROLE_USER'],
      permissions: ['organization.equipment.read'],
    ));

    $processor = new UploadCurrentUserAvatarProcessor(
      security: $security,
      uploadUserAvatarProcessor: $this->createUploadingProcessor($userId),
      queryBus: $queryBus,
    );

    $output = $processor->process(null, new Put());

    self::assertInstanceOf(CurrentUserProfileOutput::class, $output);
    self::assertSame($userId, $output->id);
    self::assertSame('user@example.com', $output->email);
    self::assertSame(['ROLE_USER'], $output->roles);
    self::assertSame(['organization.equipment.read'], $output->permissions);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertNull($output->lastLoginAt);
  }

  private function createUploadUserAvatarProcessor(): UploadUserAvatarProcessor
  {
    return new UploadUserAvatarProcessor(
      requestStack: new RequestStack(),
      avatarResizer: $this->createStub(AvatarResizer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );
  }

  /**
   * Builds a delegate that actually completes an avatar upload, so the
   * outer processor reaches its profile re-read instead of short-circuiting
   * on a null delegate result.
   */
  private function createUploadingProcessor(string $userId): UploadUserAvatarProcessor
  {
    $path = tempnam(sys_get_temp_dir(), 'avatar-');
    self::assertIsString($path);
    file_put_contents($path, base64_decode(self::PNG_1X1_BASE64, true));

    $request = Request::create('https://api.fireguard.test/api/users/me/avatar', 'POST');
    $request->files->set('avatar', new UploadedFile($path, 'avatar.png', 'image/png', null, true));

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn($this->createStub(ResultMessage::class));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult($this->makeUserView($userId)));

    return new UploadUserAvatarProcessor(
      requestStack: $requestStack,
      avatarResizer: $this->createStub(AvatarResizer::class),
      commandBus: $commandBus,
      queryBus: $queryBus,
    );
  }

  private function makeUserView(string $userId): UserView
  {
    return new UserView(
      id: $userId,
      username: 'user',
      email: 'user@example.com',
      firstName: 'Ada',
      lastName: 'Lovelace',
      avatarUrl: 'https://api.fireguard.test/api/users/' . $userId . '/avatar/256.webp',
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
    );
  }
}

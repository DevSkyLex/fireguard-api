<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Infrastructure\Image\AvatarResizer;
use User\Presentation\Api\Processor\User\{
  UploadCurrentUserAvatarProcessor,
  UploadUserAvatarProcessor
};

#[CoversClass(UploadCurrentUserAvatarProcessor::class)]
final class UploadCurrentUserAvatarProcessorTest extends TestCase
{
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

  private function createUploadUserAvatarProcessor(): UploadUserAvatarProcessor
  {
    return new UploadUserAvatarProcessor(
      requestStack: new RequestStack(),
      avatarResizer: $this->createStub(AvatarResizer::class),
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );
  }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Messaging;

use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\UploadUserAvatarHandler;

/**
 * Class FileCleanupWiringTest
 *
 * Verifies named auth bindings and default main bindings in the compiled container.
 *
 * @category Integration Test
 */
final class FileCleanupWiringTest extends KernelTestCase
{
  public function testAvatarProducerUsesTheSameAuthConnectionForRootCommitAndEnqueue(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $registry = $container->get('doctrine');
    self::assertInstanceOf(ManagerRegistry::class, $registry);
    $handler = $container->get(UploadUserAvatarHandler::class);
    self::assertInstanceOf(UploadUserAvatarHandler::class, $handler);
    $root = $this->property($handler, 'transactions');
    self::assertInstanceOf(RootTransactionManagerAdapter::class, $root);
    self::assertSame($registry->getConnection('auth'), $this->property($root, 'connection'));
    self::assertSame($registry->getManager('auth'), $this->property($root, 'entityManager'));
    $cleanup = $this->property($handler, 'cleanup');
    self::assertInstanceOf(TransactionalFileCleanupAdapter::class, $cleanup);
    self::assertSame('auth', $this->property($cleanup, 'owningDatabase'));
    $dispatcher = $this->property($cleanup, 'events');
    self::assertInstanceOf(TransactionalEventDispatcher::class, $dispatcher);
    self::assertSame($registry->getConnection('auth'), $this->property($dispatcher, 'connection'));
    self::assertSame($container->get('messenger.transport.auth_outbox'), $this->property($dispatcher, 'sender'));
    $defaultRoot = $container->get(RootTransactionManagerPort::class);
    self::assertInstanceOf(RootTransactionManagerAdapter::class, $defaultRoot);
    self::assertSame($registry->getConnection('main'), $this->property($defaultRoot, 'connection'));
    self::assertSame($registry->getManager('main'), $this->property($defaultRoot, 'entityManager'));
  }

  public function testSharedConsumerHasSeparateAuthAndMainReceiptConnections(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $registry = $container->get('doctrine');
    self::assertInstanceOf(ManagerRegistry::class, $registry);
    $subscriber = $container->get(FileCleanupSubscriber::class);
    self::assertInstanceOf(FileCleanupSubscriber::class, $subscriber);
    $main = $this->property($subscriber, 'receipts');
    $auth = $this->property($subscriber, 'authReceipts');
    self::assertInstanceOf(DoctrineIdempotentConsumerAdapter::class, $main);
    self::assertInstanceOf(DoctrineIdempotentConsumerAdapter::class, $auth);
    self::assertSame($registry->getConnection('main'), $this->property($main, 'connection'));
    self::assertSame($registry->getConnection('auth'), $this->property($auth, 'connection'));
    self::assertNotSame($auth, $main);
  }

  private function property(object $object, string $name): mixed
  {
    return new ReflectionClass($object)->getProperty($name)->getValue($object);
  }
}

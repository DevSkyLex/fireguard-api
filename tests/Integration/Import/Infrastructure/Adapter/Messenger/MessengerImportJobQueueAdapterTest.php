<?php

declare(strict_types=1);

namespace Tests\Integration\Import\Infrastructure\Adapter\Messenger;

use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\UseCase\Command\ProcessImportJob\ProcessImportJobCommand;
use Import\Infrastructure\Adapter\Messenger\MessengerImportJobQueueAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function count;

/**
 * Test MessengerImportJobQueueAdapterTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessengerImportJobQueueAdapter::class)]
final class MessengerImportJobQueueAdapterTest extends KernelTestCase
{
  #[Test]
  public function itEnqueuesTheProcessCommandOnTheAsyncTransportWithoutThrowing(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var ImportJobQueuePort $queue */
    $queue = $container->get(ImportJobQueuePort::class);

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.async');
    $before = count($transport->getSent());

    // Must not throw: this is the exact fire-and-forget call CreateImportJobHandler makes.
    $queue->dispatch('990e8400-e29b-41d4-a716-4466554d0abc');

    $sent = $transport->getSent();
    self::assertCount($before + 1, $sent, 'the import job processing must reach the async transport');

    $message = $sent[count($sent) - 1]->getMessage();
    self::assertInstanceOf(ProcessImportJobCommand::class, $message);
    self::assertSame('990e8400-e29b-41d4-a716-4466554d0abc', $message->importJobId);
  }
}

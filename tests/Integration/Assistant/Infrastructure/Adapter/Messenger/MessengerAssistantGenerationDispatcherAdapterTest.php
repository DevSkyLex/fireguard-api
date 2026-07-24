<?php

declare(strict_types=1);

namespace Tests\Integration\Assistant\Infrastructure\Adapter\Messenger;

use Assistant\Application\Port\Outbound\AssistantGenerationDispatcherPort;
use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function count;

/**
 * Test MessengerAssistantGenerationDispatcherAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(\Assistant\Infrastructure\Adapter\Messenger\MessengerAssistantGenerationDispatcherAdapter::class)]
final class MessengerAssistantGenerationDispatcherAdapterTest extends KernelTestCase
{
  #[Test]
  public function itEnqueuesTheGenerationCommandOnTheAssistantTransportWithoutThrowing(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var AssistantGenerationDispatcherPort $dispatcher */
    $dispatcher = $container->get(AssistantGenerationDispatcherPort::class);

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.assistant');
    $before = count($transport->getSent());

    // Must not throw: this is the exact call AskAssistantQuestionHandler makes.
    $dispatcher->enqueue(
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a01',
      threadId: '018f0b68-6758-7a12-8a1d-3f0d97f64a02',
      userMessageId: '018f0b68-6758-7a12-8a1d-3f0d97f64a03',
      assistantMessageId: '018f0b68-6758-7a12-8a1d-3f0d97f64a04',
      model: 'llama3.1:8b',
      temperature: 0.4,
    );

    $sent = $transport->getSent();
    self::assertCount($before + 1, $sent, 'the generation command must reach the assistant async transport');

    $message = $sent[count($sent) - 1]->getMessage();
    self::assertInstanceOf(GenerateAssistantReplyCommand::class, $message);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a01', $message->organizationId);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a02', $message->threadId);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a03', $message->userMessageId);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a04', $message->assistantMessageId);
    self::assertSame('llama3.1:8b', $message->model);
    self::assertSame(0.4, $message->temperature);
  }

  /**
   * When no tenant model/temperature override is supplied, the enqueued
   * command must carry the nulls through unchanged, so the consuming handler
   * can fall back to the operator defaults (OLLAMA_DEFAULT_MODEL, a fixed
   * default temperature) rather than a tenant-influenced value.
   */
  #[Test]
  public function itEnqueuesWithNullModelAndTemperatureWhenNoOverrideIsGiven(): void
  {
    self::bootKernel();
    $container = self::getContainer();

    /** @var AssistantGenerationDispatcherPort $dispatcher */
    $dispatcher = $container->get(AssistantGenerationDispatcherPort::class);

    /** @var InMemoryTransport $transport */
    $transport = $container->get('messenger.transport.assistant');
    $before = count($transport->getSent());

    $dispatcher->enqueue(
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a11',
      threadId: '018f0b68-6758-7a12-8a1d-3f0d97f64a12',
      userMessageId: '018f0b68-6758-7a12-8a1d-3f0d97f64a13',
      assistantMessageId: '018f0b68-6758-7a12-8a1d-3f0d97f64a14',
    );

    $sent = $transport->getSent();
    self::assertCount($before + 1, $sent, 'the generation command must reach the assistant async transport');

    $message = $sent[count($sent) - 1]->getMessage();
    self::assertInstanceOf(GenerateAssistantReplyCommand::class, $message);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a12', $message->threadId);
    self::assertNull($message->model);
    self::assertNull($message->temperature);
  }
}

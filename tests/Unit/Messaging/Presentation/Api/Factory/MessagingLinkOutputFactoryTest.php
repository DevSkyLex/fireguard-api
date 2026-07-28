<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Factory;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\MessagingLinkView;
use Messaging\Presentation\Api\Factory\MessagingLinkOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingLinkOutputFactoryTest.
 *
 * Links are exposed with a bare `messageId` rather than an IRI, and the label
 * is reserved for a future preview feature — both shapes are pinned here so a
 * well-meaning "consistency" change does not silently break the client.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingLinkOutputFactory::class)]
final class MessagingLinkOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string LINK_ID = '550e8400-e29b-41d4-a716-446655481001';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655481002';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655481003';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewMapsTheExtractedLink(): void
  {
    $output = new MessagingLinkOutputFactory()->fromView($this->view('Fire safety guide'));

    self::assertSame(self::LINK_ID, $output->id);
    self::assertSame('https://example.test/guide', $output->url);
    self::assertSame('Fire safety guide', $output->label);
    self::assertSame(self::MESSAGE_ID, $output->messageId);
  }

  #[Test]
  public function testFromViewKeepsAnUnlabelledLinkNull(): void
  {
    $output = new MessagingLinkOutputFactory()->fromView($this->view());

    self::assertNull($output->label);
  }

  #[Test]
  public function testFromViewFormatsTheExtractionDateAsIso8601(): void
  {
    $output = new MessagingLinkOutputFactory()->fromView($this->view());

    self::assertSame('2026-04-07T11:22:33+00:00', $output->createdAt);
  }

  private function view(?string $label = null): MessagingLinkView
  {
    return new MessagingLinkView(
      id: self::LINK_ID,
      messageId: self::MESSAGE_ID,
      conversationId: self::CONVERSATION_ID,
      url: 'https://example.test/guide',
      label: $label,
      createdAt: new DateTimeImmutable('2026-04-07T11:22:33+00:00'),
    );
  }
  // #endregion
}

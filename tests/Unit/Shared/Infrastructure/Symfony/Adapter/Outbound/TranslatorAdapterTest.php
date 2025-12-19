<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\TranslationException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\TranslatorAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class TranslatorAdapterTest.
 *
 * Unit tests for the TranslatorAdapter.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\TranslatorAdapter
 */
#[CoversClass(className: TranslatorAdapter::class)]
final class TranslatorAdapterTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;
    private TranslatorAdapter $adapter;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->adapter = new TranslatorAdapter($this->translator);
    }

    /**
     * Test that a message is translated successfully.
     */
    #[Test]
    public function testTranslateSuccess(): void
    {
        $id = 'message.id';
        $parameters = ['%param%' => 'value'];
        $domain = 'messages';
        $locale = 'en';
        $translated = 'Translated Message';

        $this->translator->expects($this->once())
          ->method('trans')
          ->with($id, $parameters, $domain, $locale)
          ->willReturn($translated);

        $this->assertEquals($translated, $this->adapter->translate($id, $parameters, $domain, $locale));
    }

    /**
     * Test that translation fails and throws a TranslationException.
     */
    #[Test]
    public function testTranslateThrowsException(): void
    {
        $id = 'message.id';
        $exception = new Exception('Translation error');

        $this->translator->expects($this->once())
          ->method('trans')
          ->willThrowException($exception);

        $this->expectException(TranslationException::class);
        $this->adapter->translate($id);
    }
}

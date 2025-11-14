<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\TranslatorAdapter;
use Shared\Infrastructure\Symfony\Exception\TranslationException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Test TranslatorAdapter
 * @final
 *
 * Test the TranslatorAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TranslatorAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testTranslateDelegatesToTranslator
   *
   * Ensure that the translate method delegates
   * to the Symfony translator with the provided arguments.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testTranslateDelegatesToTranslator(): void
  {
    $translator = $this->createMock(type: TranslatorInterface::class);
    $translator->expects(self::once())
      ->method(constraint: 'trans')
      ->with(
        id: 'message.id',
        parameters: ['name' => 'John'],
        domain: 'messages',
        locale: 'fr'
      )
      ->willReturn(value: 'Bonjour John');

    $adapter = new TranslatorAdapter(translator: $translator);

    $translated = $adapter->translate(
      id: 'message.id',
      parameters: ['name' => 'John'],
      domain: 'messages',
      locale: 'fr'
    );

    self::assertSame(
      expected: 'Bonjour John',
      actual: $translated
    );
  }

  /**
   * Method testTranslateWrapsExceptions
   *
   * Ensure that the adapter wraps exceptions
   * thrown by the translator.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testTranslateWrapsExceptions(): void
  {
    // Create translator mock
    $translator = $this->createMock(type: TranslatorInterface::class);

    // Configure translator to throw exception
    $translator->expects(self::once())
      ->method(constraint: 'trans')
      ->willThrowException(exception: $this->createMock(type: Throwable::class));

    // Create adapter
    $adapter = new TranslatorAdapter(translator: $translator);

    $this->expectException(exception: TranslationException::class);
    $adapter->translate(id: 'message.id');
  }
  //#endregion
}

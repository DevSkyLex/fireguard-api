<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Pdf;

use Dompdf\Adapter\CPDF;

use function array_key_last;
use function array_pop;

/**
 * Class RecordingLabelCanvas
 *
 * Records translated painting and clipping commands while executing the real CPDF
 * backend. Only translation is tracked because the label template uses no other transform.
 *
 * @category Test Support
 */
final class RecordingLabelCanvas extends CPDF
{
  // #region Properties
  /**
   * Property owner
   *
   * The label containing the frame currently being painted.
   */
  public ?string $owner = null;

  /**
   * Property texts
   *
   * @var list<array{text: string, owner: string|null, page: int, box: array{x: float, y: float, w: float, h: float}, clip: array{x: float, y: float, w: float, h: float}|null}>
   */
  public array $texts = [];

  /**
   * Property images
   *
   * @var list<array{owner: string|null, page: int, box: array{x: float, y: float, w: float, h: float}}>
   */
  public array $images = [];

  /**
   * Property clips
   *
   * @var list<array{owner: string|null, page: int, box: array{x: float, y: float, w: float, h: float}}>
   */
  public array $clips = [];

  /**
   * Property translation
   *
   * @var array{0: float, 1: float}
   */
  private array $translation = [0.0, 0.0];

  /**
   * Property translations
   *
   * @var list<array{0: float, 1: float}>
   */
  private array $translations = [];

  /**
   * Property activeClips
   *
   * @var list<array{x: float, y: float, w: float, h: float}>
   */
  private array $activeClips = [];
  // #endregion

  // #region Methods
  /**
   * Method save
   *
   * @access public
   *
   * @return void
   */
  public function save(): void
  {
    $this->translations[] = $this->translation;
    parent::save();
  }

  /**
   * Method restore
   *
   * @access public
   *
   * @return void
   */
  public function restore(): void
  {
    $this->translation = array_pop($this->translations) ?? [0.0, 0.0];
    parent::restore();
  }

  /**
   * Method translate
   *
   * @access public
   *
   * @param float $t_x horizontal translation in points
   * @param float $t_y vertical translation in points
   *
   * @return void
   */
  public function translate(mixed $t_x, mixed $t_y): void
  {
    $this->translation[0] += (float) $t_x;
    $this->translation[1] += (float) $t_y;
    parent::translate($t_x, $t_y);
  }

  /**
   * Method clipping_rectangle
   *
   * @access public
   *
   * @param float $x1 left coordinate in points
   * @param float $y1 top coordinate in points
   * @param float $w width in points
   * @param float $h height in points
   *
   * @return void
   */
  public function clipping_rectangle(mixed $x1, mixed $y1, mixed $w, mixed $h): void
  {
    $box = $this->translatedBox((float) $x1, (float) $y1, (float) $w, (float) $h);
    $this->clips[] = ['owner' => $this->owner, 'page' => $this->get_page_number(), 'box' => $box];
    $this->activeClips[] = $box;
    parent::clipping_rectangle($x1, $y1, $w, $h);
  }

  /**
   * Method clipping_end
   *
   * @access public
   *
   * @return void
   */
  public function clipping_end(): void
  {
    array_pop($this->activeClips);
    parent::clipping_end();
  }

  /**
   * Method text
   *
   * @access public
   *
   * @param float $x left coordinate in points
   * @param float $y top coordinate in points
   * @param string $text text to paint
   * @param string $font font file
   * @param float $size font size in points
   * @param array<float|int> $color text colour
   * @param float $word_space extra word spacing
   * @param float $char_space extra character spacing
   * @param float $angle text angle
   *
   * @return void
   */
  public function text(mixed $x, mixed $y, mixed $text, mixed $font, mixed $size, mixed $color = [0, 0, 0], mixed $word_space = 0.0, mixed $char_space = 0.0, mixed $angle = 0.0): void
  {
    $this->texts[] = [
      'text' => (string) $text,
      'owner' => $this->owner,
      'page' => $this->get_page_number(),
      'box' => $this->translatedBox((float) $x, (float) $y, $this->get_text_width($text, $font, $size, $word_space, $char_space), $this->get_font_height($font, $size)),
      'clip' => $this->activeClips[array_key_last($this->activeClips)] ?? null,
    ];
    parent::text($x, $y, $text, $font, $size, $color, $word_space, $char_space, $angle);
  }

  /**
   * Method image
   *
   * @access public
   *
   * @param string $img local image path
   * @param float $x left coordinate in points
   * @param float $y top coordinate in points
   * @param float $w width in points
   * @param float $h height in points
   * @param string $resolution image resolution
   *
   * @return void
   */
  public function image(mixed $img, mixed $x, mixed $y, mixed $w, mixed $h, mixed $resolution = 'normal'): void
  {
    $this->images[] = ['owner' => $this->owner, 'page' => $this->get_page_number(), 'box' => $this->translatedBox((float) $x, (float) $y, (float) $w, (float) $h)];
    parent::image($img, $x, $y, $w, $h, $resolution);
  }

  /**
   * Method translatedBox
   *
   * @access public
   *
   * @param float $x left coordinate in points
   * @param float $y top coordinate in points
   * @param float $width width in points
   * @param float $height height in points
   *
   * @return array{x: float, y: float, w: float, h: float} the painted box
   */
  public function translatedBox(float $x, float $y, float $width, float $height): array
  {
    return ['x' => $x + $this->translation[0], 'y' => $y + $this->translation[1], 'w' => $width, 'h' => $height];
  }
  // #endregion
}

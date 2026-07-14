<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\DefaultTheme;

/**
 * Test fixture: widens the theme's protected render helpers to public.
 *
 * The helpers are protected on the theme - they are override hooks for custom
 * themes, not consumer API - so tests exercising one helper in isolation use
 * this subclass instead of the public entry points.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Theme
 */
final class ExposedTheme extends DefaultTheme {

  // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
  // Each override exists purely to widen a protected helper to public; the
  // parent call is the entire point, not an oversight.

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): string {
    return parent::renderFieldLine($field, $answers, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderPanelLine(Panel $panel, bool $selected): string {
    return parent::renderPanelLine($panel, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderDescriptionLine(string $description, bool $selected): string {
    return parent::renderDescriptionLine($description, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function summarizePanel(Panel $panel, Answers $answers): string {
    return parent::summarizePanel($panel, $answers);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderSummaryLine(string $summary, bool $selected): string {
    return parent::renderSummaryLine($summary, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderHintLine(string ...$hints): string {
    return parent::renderHintLine(...$hints);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderEditorHeader(string $label): string {
    return parent::renderEditorHeader($label);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function keysHint(ScopedKeyMap $keys, string $label, Action ...$actions): string {
    return parent::keysHint($keys, $label, ...$actions);
  }

}

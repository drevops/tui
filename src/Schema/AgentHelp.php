<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Config\DateBounds;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\NumberBounds;
use DrevOps\Tui\Translation\Translator;

/**
 * Produces instructions for driving the form non-interactively.
 *
 * @package DrevOps\Tui\Schema
 */
class AgentHelp {

  /**
   * Construct the help generator.
   *
   * @param \DrevOps\Tui\Config\Config $config
   *   The configuration to describe.
   * @param string $env_prefix
   *   The prefix for per-question env variable names (e.g. "APP_").
   */
  public function __construct(protected Config $config, protected string $envPrefix = '') {
  }

  /**
   * Generate the agent help text.
   *
   * @return string
   *   The instructions.
   */
  public function generate(): string {
    $lines = [
      Translator::t('Drive the form non-interactively:'),
      '',
      Translator::t('- Pass --no-interaction to resolve every question from defaults, discovery and derivation without prompting.'),
      Translator::t('- Pass --prompts with a JSON object (or a path to a JSON file) of answers keyed by question id; these take the highest precedence.'),
    ];

    if ($this->envPrefix !== '') {
      $lines[] = Translator::t('- Set per-question environment variables named @prefix<ID> (the uppercased question id); these win over discovery but lose to --prompts.', [
        '@prefix' => $this->envPrefix,
      ]);
    }

    $lines[] = Translator::t('- Precedence: --prompts > environment > discovered > derived > default.');
    $lines[] = '';
    $lines[] = Translator::t('Questions:');

    foreach ($this->config->fields() as $field) {
      $required = $field->required ? ' ' . Translator::t('(required)') : '';
      $lines[] = sprintf('  %s [%s]%s - %s%s%s', $field->id, $field->type->value, $required, Translator::t($field->label), $this->rangeNote($field), $this->dateNote($field));
    }

    return implode("\n", $lines);
  }

  /**
   * A compact range annotation for a bounded number field.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   *
   * @return string
   *   The annotation (e.g. " (between 1 and 10, step 2)"), or an empty string.
   */
  protected function rangeNote(Field $field): string {
    if (!$field->bounds instanceof NumberBounds) {
      return '';
    }

    $parts = [];

    $described = $field->bounds->describe();
    if ($described !== '') {
      $parts[] = $described;
    }

    if ($field->bounds->step !== NULL) {
      $parts[] = Translator::t('step @step', ['@step' => $field->bounds->step]);
    }

    return $parts === [] ? '' : sprintf(' (%s)', implode(', ', $parts));
  }

  /**
   * A format-and-range annotation for a date field.
   *
   * @param \DrevOps\Tui\Config\Field $field
   *   The field.
   *
   * @return string
   *   The annotation (e.g. " (between 2026-01-01 and 2026-12-31)"), or an empty
   *   string when the field is not a date.
   */
  protected function dateNote(Field $field): string {
    if (!$field->dateBounds instanceof DateBounds) {
      return '';
    }

    $described = $field->dateBounds->describe();

    return sprintf(' (%s)', $described === '' ? 'YYYY-MM-DD' : $described);
  }

}

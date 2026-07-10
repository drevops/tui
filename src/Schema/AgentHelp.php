<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Config\Config;
use DrevOps\Tui\Config\Field;
use DrevOps\Tui\Config\NumberBounds;

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
   *   The prefix for per-question env variable names (e.g. "VORTEX_").
   */
  public function __construct(protected Config $config, protected string $env_prefix = '') {
  }

  /**
   * Generate the agent help text.
   *
   * @return string
   *   The instructions.
   */
  public function generate(): string {
    $lines = [
      'Drive the form non-interactively:',
      '',
      '- Pass --no-interaction to resolve every question from defaults, discovery and derivation without prompting.',
      '- Pass --prompts with a JSON object (or a path to a JSON file) of answers keyed by question id; these take the highest precedence.',
    ];

    if ($this->env_prefix !== '') {
      $lines[] = sprintf('- Set per-question environment variables named %s<ID> (the uppercased question id); these win over discovery but lose to --prompts.', $this->env_prefix);
    }

    $lines[] = '- Precedence: --prompts > environment > discovered > derived > default.';
    $lines[] = '';
    $lines[] = 'Questions:';

    foreach ($this->config->fields() as $field) {
      $required = $field->required ? ' (required)' : '';
      $lines[] = sprintf('  %s [%s]%s - %s%s', $field->id, $field->type->value, $required, $field->label, $this->rangeNote($field));
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
      $parts[] = sprintf('step %d', $field->bounds->step);
    }

    return sprintf(' (%s)', implode(', ', $parts));
  }

}

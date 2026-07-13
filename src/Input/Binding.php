<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * One authored binding: an action reachable by a set of keys within a scope.
 *
 * This is the declaration unit a preset ships and a consumer overrides with.
 * Keys are given in their most convenient form - a {@see KeyName} for a named
 * key, a single-character string for a printable key, or a ready {@see Key} -
 * and {@see KeyMap} normalizes them to {@see Key} when it resolves. Two
 * bindings for the same scope and action do not merge: the later one wins, so a
 * consumer replaces a preset's binding by re-declaring it.
 *
 * @package DrevOps\Tui\Input
 */
final readonly class Binding {

  /**
   * The keys bound to the action, before normalisation.
   *
   * @var list<\DrevOps\Tui\Input\Key|\DrevOps\Tui\Input\KeyName|string>
   */
  public array $keys;

  /**
   * Construct a binding.
   *
   * @param \DrevOps\Tui\Input\Scope $scope
   *   The scope the binding applies in.
   * @param \DrevOps\Tui\Input\Action $action
   *   The action the keys trigger.
   * @param \DrevOps\Tui\Input\Key|\DrevOps\Tui\Input\KeyName|string ...$keys
   *   The keys bound to the action.
   */
  public function __construct(
    public Scope $scope,
    public Action $action,
    Key|KeyName|string ...$keys,
  ) {
    $this->keys = array_values($keys);
  }

}

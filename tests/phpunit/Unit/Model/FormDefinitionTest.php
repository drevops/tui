<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Panel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the root form-definition model.
 */
#[CoversClass(FormDefinition::class)]
#[Group('model')]
final class FormDefinitionTest extends TestCase {

  public function testFieldsFlattensTreeInOrder(): void {
    $form = new FormDefinition('T', 'S', [
      new Panel('a', 'A', '', [new Field('f1', 'F1', '', FieldType::Text, '')], [
        new Panel('b', 'B', '', [new Field('f2', 'F2', '', FieldType::Text, '')]),
      ]),
      new Panel('c', 'C', '', [new Field('f3', 'F3', '', FieldType::Text, '')]),
    ]);

    $ids = array_map(static fn(Field $field): string => $field->id, $form->fields());

    $this->assertSame(['f1', 'f2', 'f3'], $ids);
  }

  public function testFieldFindsByIdAcrossTree(): void {
    $form = new FormDefinition('T', 'S', [
      new Panel('a', 'A', '', [new Field('top', 'T', '', FieldType::Text, '')], [
        new Panel('b', 'B', '', [new Field('deep', 'D', '', FieldType::Text, '')]),
      ]),
    ]);

    $this->assertSame('top', $form->field('top')?->id);
    $this->assertSame('deep', $form->field('deep')?->id);
    $this->assertNotInstanceOf(Field::class, $form->field('missing'));
  }

}

<?php

/**
 * @file
 * Spanish catalog for the translations demo.
 *
 * A catalog is a per-language PHP file returning a source => translation
 * map. The English source string is the key - chrome strings and the form's
 * own labels alike - and placeholders such as "@count" stay verbatim. An
 * untranslated string simply renders in English, so a catalog can start
 * small and grow. The library's full chrome key list lives in
 * translations/tui.php at the repository root.
 */

declare(strict_types=1);

return [
  // Chrome the TUI itself renders.
  '(empty)' => '(vacío)',
  '(required)' => '(obligatorio)',
  '@count selected' => '@count seleccionados',
  'Keyboard help' => 'Ayuda del teclado',
  'Navigation' => 'Navegación',
  'No' => 'No',
  'Press any key to continue...' => 'Pulse cualquier tecla para continuar...',
  'Submit' => 'Enviar',
  'Cancel' => 'Cancelar',
  'Yes' => 'Sí',
  // The summary's lowercase yes/no are their own keys.
  'yes' => 'sí',
  'no' => 'no',
  // The form's own questions and options.
  'New order' => 'Pedido nuevo',
  'Order name' => 'Nombre del pedido',
  'Fruit' => 'Fruta',
  'Quantity' => 'Cantidad',
  'Organic only?' => '¿Solo ecológico?',
  'Apple' => 'Manzana',
  'Banana' => 'Plátano',
  'Cherry' => 'Cereza',
];

<?php

/**
 * @file
 * Spanish catalog for the translations demo.
 *
 * A catalog is a per-language PHP file returning a source => translation map.
 * The English source string is the key - chrome strings and the form's own
 * labels alike - and placeholders such as "@count" stay verbatim. A plural
 * message maps its plural source to a list of forms; Spanish has two, so no
 * rule is needed and the default one-versus-other rule applies. An untranslated
 * string simply renders in English. The library's full chrome key list lives in
 * translations/en.php at the repository root.
 */

declare(strict_types=1);

return [
  // Chrome the TUI itself renders.
  '(empty)' => '(vacío)',
  '@count items selected' => [
    '1 elemento seleccionado',
    '@count elementos seleccionados',
  ],
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
  'Your weekly produce order.' => 'Su pedido semanal de productos.',
  'Order name' => 'Nombre del pedido',
  'Basket' => 'Cesta',
  'Pick your fruits.' => 'Elija sus frutas.',
  'Fruits' => 'Frutas',
  'Apple' => 'Manzana',
  'Banana' => 'Plátano',
  'Cherry' => 'Cereza',
  'Pear' => 'Pera',
  'Grape' => 'Uva',
];

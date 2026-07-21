<?php

/**
 * @file
 * Ukrainian catalog for the translations demo.
 *
 * Named for the ISO 639-1 language code "uk" (Ukrainian) - not the country
 * code "ua". The library resolves a locale to its primary subtag (uk_UA -> uk),
 * so this file loads for any Ukrainian locale.
 *
 * @see https://en.wikipedia.org/wiki/List_of_ISO_639_language_codes
 *
 * Ukrainian has three plural forms, so the catalog supplies its own rule under
 * the reserved key and lists all three forms; the count picks one of them.
 */

declare(strict_types=1);

use DrevOps\Tui\Translation\Translator;

return [
  // The count-to-form rule: 0 one, 1 few, 2 many (Unicode CLDR for Ukrainian).
  Translator::PLURAL_RULE => static function (int $count): int {
    $mod10 = $count % 10;
    $mod100 = $count % 100;

    if ($mod10 === 1 && $mod100 !== 11) {
      return 0;
    }

    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
      return 1;
    }

    return 2;
  },
  // The plural forms, in rule order: one, few, many.
  '@count items selected' => [
    '@count елемент вибрано',
    '@count елементи вибрано',
    '@count елементів вибрано',
  ],
  // Chrome the TUI itself renders.
  '(empty)' => '(порожньо)',
  'Keyboard help' => 'Довідка з клавіатури',
  'Navigation' => 'Навігація',
  'No' => 'Ні',
  'Press any key to continue...' => 'Натисніть будь-яку клавішу, щоб продовжити...',
  'Submit' => 'Надіслати',
  'Cancel' => 'Скасувати',
  'Yes' => 'Так',
  // The summary's lowercase yes/no are their own keys.
  'yes' => 'так',
  'no' => 'ні',
  // The form's own questions and options.
  'New order' => 'Нове замовлення',
  'Your weekly produce order.' => 'Ваше щотижневе замовлення продукції.',
  'Order name' => 'Назва замовлення',
  'Basket' => 'Кошик',
  'Pick your fruits.' => 'Виберіть фрукти.',
  'Fruits' => 'Фрукти',
  'Apple' => 'Яблуко',
  'Banana' => 'Банан',
  'Cherry' => 'Вишня',
  'Pear' => 'Груша',
  'Grape' => 'Виноград',
  'Plum' => 'Слива',
];

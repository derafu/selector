<?php

declare(strict_types=1);

/**
 * Derafu: Selector - Elegant Data Structure Navigation for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'The comparison key "{key}" must contain a bool, int, float, string, or Stringable; {type} given.' =>
        'La clave de comparación "{key}" debe contener bool, int, float, string o Stringable; se recibió {type}.',
    'The value to concatenate must be a bool, int, float, string, Stringable, or null; {type} given.' =>
        'El valor a concatenar debe ser bool, int, float, string, Stringable o null; se recibió {type}.',
    'The condition operand must be a bool, int, float, string, Stringable, or null; {type} given.' =>
        'El operando de la condición debe ser bool, int, float, string, Stringable o null; se recibió {type}.',
    'Evaluator error: {message}' =>
        'Error de evaluación: {message}',
    'Parser error: {message}' =>
        'Error de análisis: {message}',
    'Type error: {message}' =>
        'Error de tipo: {message}',
    'Unsupported operation: {message}' =>
        'Operación no admitida: {message}',
    'Failed to read using selector \'\'{selector}\'\': {error}' =>
        'No se pudo leer usando el selector \'\'{selector}\'\': {error}',
    'Failed to write using selector \'\'{selector}\'\': {error}' =>
        'No se pudo escribir usando el selector \'\'{selector}\'\': {error}',
    'Failed to clear using selector \'\'{selector}\'\': {error}' =>
        'No se pudo limpiar usando el selector \'\'{selector}\'\': {error}',
    'Operator not implemented: {operator}' =>
        'Operador no implementado: {operator}',
    'Cannot access array elements on non-array at key \'\'{key}\'\'' =>
        'No se puede acceder a elementos de un arreglo en un valor que no es un arreglo en la clave \'\'{key}\'\'',
    'Cannot traverse through non-array value at dependent key \'\'{key}\'\'' =>
        'No se puede recorrer un valor que no es un arreglo en la clave dependiente \'\'{key}\'\'',
    'Cannot traverse through non-array value at key \'\'{key}\'\'' =>
        'No se puede recorrer un valor que no es un arreglo en la clave \'\'{key}\'\'',
    'Cannot access index on non-array at key \'\'{key}\'\'' =>
        'No se puede acceder a un índice en un valor que no es un arreglo en la clave \'\'{key}\'\'',
    'Cannot traverse through non-array value at index {index}' =>
        'No se puede recorrer un valor que no es un arreglo en el índice {index}',
    'Invalid JSONPath expression: {expression}' =>
        'Expresión JSONPath inválida: {expression}',
    'Invalid JMESPath expression: {expression}' =>
        'Expresión JMESPath inválida: {expression}',
    'Write operations are not supported with JMESPath selectors.' =>
        'Las operaciones de escritura no están admitidas con selectores JMESPath.',
    'Clear operations are not supported with JMESPath selectors.' =>
        'Las operaciones de limpieza no están admitidas con selectores JMESPath.',
    'Write operations are not supported with JSONPath selectors.' =>
        'Las operaciones de escritura no están admitidas con selectores JSONPath.',
    'Clear operations are not supported with JSONPath selectors.' =>
        'Las operaciones de limpieza no están admitidas con selectores JSONPath.',
    'Write operations are not supported with literal selectors' =>
        'Las operaciones de escritura no están admitidas con selectores literales',
    'Clear operations are not supported with literal selectors' =>
        'Las operaciones de limpieza no están admitidas con selectores literales',
    'Write not supported for composite selectors.' =>
        'La escritura no está admitida para selectores compuestos.',
    'Clear not supported for composite selectors.' =>
        'La limpieza no está admitida para selectores compuestos.',
];

# Derafu: Selector - Elegant Data Structure Navigation for PHP

[![CI Workflow](https://github.com/derafu/selector/actions/workflows/ci.yml/badge.svg?branch=main&event=push)](https://github.com/derafu/selector/actions/workflows/ci.yml?query=branch%3Amain)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A powerful, flexible, and efficient PHP library for querying, extracting, and modifying complex data structures using a **declarative selector syntax**.

## Why Derafu\Selector?

### 🚀 **A Smarter Way to Work with Data Structures**

Traditional PHP methods for accessing and modifying nested data structures (`$array['key']['subkey']`) are:

- **Error-prone**: Risk of `Undefined index` or `Trying to access array offset on null` errors.
- **Verbose & Hard to Read**: Requires multiple `isset()` checks and deep nesting.
- **Limited**: No built-in support for **filters, conditional selectors, or transformations**.

### 🔥 **What Makes Derafu\Selector Unique?**

| Feature                              | Derafu\Selector | PHP Native (`isset()` & loops)   | Other Libraries |
|--------------------------------------|-----------------|----------------------------------|-----------------|
| **Dot-Notation Access**              | ✅ Yes          | ❌ No                            | ⚠️ Limited       |
| **Nested Selection with Conditions** | ✅ Yes          | ❌ No                            | ⚠️ Limited       |
| **Dynamic Path Resolution**          | ✅ Yes          | ❌ No                            | ⚠️ Varies        |
| **Auto-Handles Undefined Keys**      | ✅ Yes          | ❌ No                            | ⚠️ Partial       |
| **Filters & Expressions**            | ✅ Yes          | ❌ No                            | ⚠️ Partial       |

---

## Features

- ✅ **Powerful Selector Syntax** – Access deeply nested structures with dot-notation, array indices, and conditionals.
- ✅ **Works with Arrays & Objects** – Query any data structure seamlessly.
- ✅ **Built-in Filtering** – Supports conditions like `users[id=123]` or `items[price>10]`.
- ✅ **Chaining & Fluent API** – Perform multiple operations in a single statement.
- ✅ **Zero Dependencies** – Lightweight and optimized for performance.

---

## Installation

Install via Composer:

```bash
composer require derafu/selector
```

## Basic Usage

```php
use Derafu\Selector\Selector;

$data = [
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => [
            'city' => 'New York',
            'zip' => '10001'
        ]
    ]
];

// Access nested data easily.
$name = Selector::get($data, 'user.name'); // John Doe
$city = Selector::get($data, 'user.address.city'); // New York

// Modify data.
Selector::set($data, 'user.phone', '123-456-7890');
```

## Contributing

Contributions are welcome! Feel free to submit a Pull Request.

## License

This library is licensed under the MIT License. See the `LICENSE` file for more details.

## PHP 8.6

`SortDirection` is a native enum (`SortDirection::Ascending`, `SortDirection::Descending`); LaraGram's `LaraGram\Support\SortDirection` is an alias of it, so both work with `sortBy`, `orderBy`, and `Arr::sortRecursive`.

Avoid what PHP 8.6 deprecates, so code runs without warnings on PHP 8.3 through 8.6:

- `spl_object_id()` instead of `spl_object_hash()`; `is_float()` / `is_int()` / `floatval()` instead of `is_double()`, `is_long()`, `is_integer()` and `doubleval()`.
- `Collator::compare()` / `Collator::sort()` instead of `strcoll()` and the `SORT_LOCALE_STRING` flag.
- `$value instanceof $class` (or `is_a($value, $class, true)` for class names) instead of `is_a()` with a string and no `$allow_string`.
- No `return` inside a `finally` block, no `namespace` class constant, and no class, function, or constant named `is` or `let`.
- A `SessionHandlerInterface` implementation must also define `create_sid()` and `validateId()`.
- `get_object_vars($object)` before `http_build_query()` or `array_walk()` instead of passing an object.
- `str_getcsv()` / `fgetcsv()` instead of `SplFileObject::fgetcsv()` and the other `SplFileObject` CSV methods.

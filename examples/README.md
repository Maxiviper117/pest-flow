# Pest Flow examples

These examples are Pest test files, so they run with Pest's normal test context and assertions.

## Contractor activation

[contractor-activation.php](./contractor-activation.php) shows a complete Feature → Rule → Scenario flow, shared state between Given/When/Then steps, and inspection of the runtime registry and generated node IDs.

Run it from the project root after installing the Composer dependencies:

~~~sh
vendor/bin/pest examples/contractor-activation.php
~~~

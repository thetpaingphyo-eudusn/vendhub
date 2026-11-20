# VendHub Tests

Dedicated automated QA suite for the vending-machine project (PHPUnit 11).

## Folder layout

```text
tests/
  README.md
  bootstrap.php
  Controller/       # controller tests with DI + mocks (PDF 14–15)
  Integration/      # multi-layer flows (router, auth register, purchase, API)
  Unit/
    Validation/     # positive + negative input rules
    Model/
    Service/
    Middleware/
    Security/       # XSS / SQLi guards / rate limit
    Database/
    Routing/
    View/
```

## Test types

| Type | Meaning | Where |
|---|---|---|
| Positive | Valid input succeeds | Unit + Controller + Integration |
| Negative | Invalid/unauthorized input fails | Unit + Controller + Integration |
| Integration | Multiple layers together | `tests/Integration` |
| Regression | Full suite re-run to catch breakages | `composer test` / `composer test:regression` |

## Run

```bash
composer test
composer test:regression   # alias of full suite

# targeted
php vendor/bin/phpunit --testsuite Unit
php vendor/bin/phpunit --testsuite Controller
php vendor/bin/phpunit --testsuite Integration
```

Controller/Integration tests mock databases where needed and do **not** require MySQL to be running.

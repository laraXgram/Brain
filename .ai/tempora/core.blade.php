# Tempora

- Tempora is LaraGram's date and time library, a rewrite of Carbon. Use `LaraGram\Support\Tempora` (or the `now()` / `today()` helpers) instead of `Carbon\Carbon`; the Carbon API applies, so `Tempora::now()->addDays(3)->diffForHumans()` works as expected.
- Model date attributes are cast to Tempora instances. Do not add `nesbot/carbon` as a dependency.

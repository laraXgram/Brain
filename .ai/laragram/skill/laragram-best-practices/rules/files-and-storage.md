# Files and Storage Best Practices

## Use Disks, Not Raw Paths

Read and write files through the `Storage` facade and the disks in `config/filesystems.php` (`local` → `storage/app/private`, `public` → `storage/app/public`, `s3`):

```php
use LaraGram\Support\Facades\Storage;

$path = Storage::disk('public')->putFile('avatars', $request->file('avatar'));
$url = Storage::disk('public')->url($path);

Storage::disk('local')->put("exports/{$export->id}.csv", $csv);
```

- Keep private files (exports, invoices, user documents) on a private disk and serve them through an authorized controller (`Storage::download()`), not from `public/`.
- Run `php laragram storage:link` once per environment for the public disk.
- Generate file names yourself (ids, UUIDs, `fileUniqueId()`); never build paths from user input.

## Files From Telegram

- Validate size and type before downloading: `$request->file()->last()->fileSize()`, `isPhoto()`, and the document's MIME type.
- Download with `$media->download($path, $disk)` into a dedicated directory, or store only the `file_id` when the file just needs to be re-sent by the same bot.
- Bot API downloads are limited to 20 MB (larger files need a self-hosted Bot API server or MTProto).
- Download large or many files in a queued job, not in the webhook handler.

## Files To Telegram

- Reuse stored `file_id`s. Upload local files with `new \CURLFile(Storage::disk('local')->path($path))`, or pass a public HTTPS URL.
- Delete temporary files after uploading (`Storage::delete()` in a `finally` block or a scheduled cleanup).

## Uploads From Web and Mini Apps

- Validate with rules (`file`, `image`, `mimes:`, `max:`) in a form request before storing.
- Store with `putFile` / `store` so names are generated, and keep the original client name only as metadata.
- Under Surge, don't keep file handles or temporary uploads across requests.

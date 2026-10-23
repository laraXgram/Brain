---
name: image-manipulation
description: "Processes images with LaraGram's Image component. Activate when resizing, cropping, converting, compressing, or inspecting images: photos or image documents sent to the bot ($request->image(), MediaFile::image()), files uploaded to web routes or Mini Apps ($request->image('avatar'), Image::fromUpload), files on storage disks (Storage::disk()->image(), Image::fromStorage), URLs, paths, streams, base64 or raw bytes (the Image facade), sending a processed image back to Telegram (toInputFile with sendPhoto / sendDocument), returning an image from a web route, storing processed images (store, storeAs, storePublicly), thumbnails, avatars, watermark-free conversions to WebP/JPEG/PNG/AVIF, config/images.php and IMAGE_DRIVER (gd, imagick), custom image drivers (Image::extend) or custom transformations (Image::transformUsing). Also use when the user mentions intervention/image, GD, Imagick, thumbnails, avatars, or image quality."
license: MIT
metadata:
  author: laraxgram
---

# Image Manipulation

LaraGram's Image component (`LaraGram\Image`, the `Image` facade) is the port of Laravel's image API. It wraps an immutable, lazily-loaded `LaraGram\Image\Image` with a transformation pipeline that is encoded once, at the end. Verify exact options with `search-docs` (`images`, `image manipulation`).

## Requirements First

- The built-in `gd` and `imagick` drivers are part of LaraGram and need no Composer package. Never suggest installing `intervention/image`: LaraGram no longer uses it.
- The driver needs the matching PHP extension (`gd` or `imagick`); the `gd` driver uses the `exif` extension, when installed, to orient JPEG photos. The default driver comes from `config/images.php` (`'default' => env('IMAGE_DRIVER', 'gd')`); publish it with `php laragram config:publish images` only when the project needs to change it.
- Supported inputs: JPEG, PNG, GIF, WebP, AVIF, HEIC, BMP. Anything else (SVG, PDF, TIFF) throws `LaraGram\Image\ImageException`. HEIC output needs the `imagick` driver.
- Animations: the `gd` driver keeps only the first frame of an animated GIF; use `imagick` when animated GIF / WebP images must stay animated.

## Reading Images

| Source | Code |
| --- | --- |
| Photo or image document in the current bot update | `$request->image()` (`LaraGram\Request\Request`; largest photo size, or a document with an `image/*` MIME type; `null` otherwise) |
| A specific Telegram file | `$request->file()->first()->image()`, `$request->fileFrom($message)?->last()->image()` (any `MediaFile`) |
| Web or Mini App upload | `$request->image('avatar')` (`LaraGram\Http\Request`; `null` when missing), `Image::fromUpload($file)` |
| Storage disk | `Storage::disk('public')->image($path)`, `Image::fromStorage($path, 'public')` |
| Other | `Image::fromPath()`, `Image::fromUrl()`, `Image::fromBytes()`, `Image::fromBase64()`, `Image::fromStream()` |

Sources are read lazily: a Telegram file is downloaded through `getFile` only when the image is first processed or read, so creating the instance is cheap.

## Transforming and Encoding

```php
$image = $request->image()
    ->orient()                 // EXIF rotation (photos are already oriented while decoded; kept for clarity)
    ->cover(512, 512)          // fill and crop to exactly 512x512
    ->toWebp()
    ->quality(80);
```

- **Immutable:** every method returns a new instance. Always reassign (`$image = $image->scale(800)`); a bare `$image->scale(800);` does nothing.
- Pick the right sizing method: `cover` (fill and crop, for avatars and thumbnails), `contain` (fit with padding; background as hex `'#ffffff'`, `rgb()` / `rgba()`, `'transparent'` or `'dominant'`, white by default), `scale` (fit inside, never upscales — the safe default for "make it smaller"), `resize` (exact; passing only `width:` keeps the original height, so it distorts), `crop(w, h, x, y)`.
- Effects: `rotate($deg, $background)`, `blur(0-100)`, `sharpen(0-100)`, `grayscale()`, `flipVertically()` / `flip()`, `flipHorizontally()` / `flop()`.
- Formats: `toWebp`, `toJpg`, `toPng`, `toGif`, `toAvif`, `toHeic`, `toBmp`, or `toFormat('jpg')`; `quality(1-100)`; `optimize()` is WebP at quality 70. Without a format, the original format is kept.
- Conditional steps use `when()` / `unless()`.
- Output: `toBytes()`, `toBase64()`, `toDataUri()` (also `(string) $image`).
- Inspect the processed result with `width()`, `height()`, `dimensions()`, `mimeType()`, `extension()`, `dominantColor()`.

## Sending to Telegram

`toInputFile(?string $name = null)` encodes the image and returns a `CURLStringFile`, which every Bot API upload parameter accepts. Nothing is written to disk:

```php
Bot::onPhoto(function (Request $request) {
    $photo = $request->image()?->scale(1280, 1280)->toJpg()->quality(85);

    if (! $photo) {
        return $request->sendMessage(chat()->id, 'Please send a photo.');
    }

    $request->sendPhoto(chat()->id, $photo->toInputFile());
});
```

- Never pass the `Image` object itself to a Bot API method: it is JSON-encoded instead of uploaded.
- `sendPhoto` recompresses and is limited to 10 MB, with width + height at most 10000 and an aspect ratio of at most 20. Scale photos down before sending (`scale(1280, 1280)` or `scale(2560, 2560)`). Use `sendDocument` when the exact bytes matter.
- After the first upload, store the `file_id` from the response and reuse it instead of processing and uploading again.
- Bot API downloads are limited to 20 MB: check `$request->file()->last()->fileSize()` before processing large documents.

## Storing and Serving

- `store($dir, $disk)`, `storePublicly($dir, $disk)` generate a random name with the right extension (`hashName()`); `storeAs($dir, $name, $disk)` / `storePubliclyAs(...)` use a given name. They return the path, or `false` on failure.
- An `Image` is `Responsable`: return it from a web route or controller to serve the processed bytes with the correct `Content-Type`.

## Performance and Queues

- Processing is CPU and memory heavy. Webhook updates already run in a background process, but large images, batches, or albums should go to a queued job.
- `Image` instances **cannot be serialized**. Store the original first (`$request->file()->last()->download($path)` or `$request->image()->store('incoming')`) and pass the path to the job, which reopens it with `Image::fromStorage($path)`.
- Under Surge, don't keep `Image` instances or image bytes in static properties or singletons; the image manager is scoped per request.

## Drivers and Extensions

- Per-image driver: `->using('imagick')`, `->usingGd()`, `->usingImagick()`.
- Custom driver: implement `LaraGram\Contracts\Image\Driver` (`process`, `dimensions`, `dominantColor`, `transformUsing`) and register it in a service provider's `boot` with `Image::extend('vips', fn ($app) => new VipsDriver)`.
- Custom transformation: a class implementing `LaraGram\Contracts\Image\Transformation` with public readonly properties, registered per driver with `Image::transformUsing()`, and applied with `->transform(new Pixelate(12))`. The handler receives the driver's native image (`GdImage` for `gd`, `Imagick` for `imagick`) and must return it, e.g. `Image::transformUsing('gd', Pixelate::class, function (GdImage $image, Pixelate $t) { imagefilter($image, IMG_FILTER_PIXELATE, $t->size, true); return $image; })`. An `imagick` handler should loop over the frames (`foreach ($image as $frame)`) so animations keep working. Register a handler for every driver the application uses (`gd` and `imagick`), or the transformation throws on the missing one.

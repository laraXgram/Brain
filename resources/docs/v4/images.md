# Image Manipulation

<a name="introduction"></a>
## Introduction

LaraGram provides a fluent image manipulation API that allows you to resize, crop, encode, and store images using the same expressive conventions found throughout the framework. LaraGram's image features are powered by [Intervention Image](https://image.intervention.io/) and support the GD and Imagick PHP extensions.

The image API is useful when working with photos and documents sent to your bot, files uploaded to your web routes or Mini Apps, files stored on LaraGram [filesystem disks](/v4/filesystem), local files, remote URLs, or raw image bytes:

```php
use LaraGram\Support\Facades\Image;

$path = Image::fromStorage('avatars/photo.jpg', 'public')
    ->cover(400, 400)
    ->toWebp()
    ->quality(80)
    ->storePublicly('avatars', 'public');
```

> [!WARNING]
> Image manipulation can be CPU and memory-intensive. Consider performing large image processing workloads on a [queued job](/v4/queues) instead of during the update or HTTP request that receives the image.

<a name="installation"></a>
## Installation

Before using LaraGram's image manipulation features, install the Intervention Image package via Composer:

```shell
composer require intervention/image:^4.0
```

You should also ensure your PHP installation has either the GD or Imagick extension installed, depending on which driver your application will use.

<a name="configuration"></a>
### Configuration

LaraGram's image configuration file is located at `config/images.php`. If your application does not have an `images` configuration file, you may publish it using the `config:publish` Commander command:

```shell
php laragram config:publish images
```

The image configuration file allows you to specify your application's default image driver. You may also specify the default driver using the `IMAGE_DRIVER` environment variable. The supported drivers are `gd` and `imagick`:

```ini
IMAGE_DRIVER=imagick
```

<a name="reading-images"></a>
## Reading Images

The `Image` facade provides several methods for reading images from common sources. Image contents are loaded lazily, so the source is typically not read until the image is processed or its bytes are requested.

<a name="telegram-files"></a>
### Telegram Files

You may retrieve the image attached to an incoming bot update using the `image` method of the `LaraGram\Request\Request` instance. For photos, this method returns an `LaraGram\Image\Image` instance for the largest available size. Documents are also accepted when their MIME type is an image type. If the update does not contain an image, `null` is returned:

```php
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

Bot::onPhoto(function (Request $request) {
    $path = $request->image()
        ->cover(400, 400)
        ->toWebp()
        ->storePublicly('avatars', 'public');

    // ...
});
```

The file is downloaded from the Bot API server only when the image is first processed or read. You may also create an image instance from any `LaraGram\Request\Files\MediaFile` using its `image` method, which is useful when you want to choose a specific photo size or work with the files of a message other than the current one:

```php
// The smallest size of the photo...
$image = $request->file()->first()->image();

// The image of the message the user replied to...
if ($reply = $request->message->reply_to_message) {
    $image = $request->fileFrom($reply)?->last()->image();
}
```

> [!NOTE]
> The Bot API only allows bots to download files up to 20 MB. Larger files require a self-hosted Bot API server.

<a name="uploaded-files"></a>
### Uploaded Files

You may retrieve an uploaded image from an incoming HTTP request using the `image` method. This method returns an `LaraGram\Image\Image` instance for the uploaded file, or `null` if the file is not present:

```php
use LaraGram\Http\Request;

Route::post('/avatar', function (Request $request) {
    $request->validate(['avatar' => ['required', 'image']]);

    $path = $request->image('avatar')
        ->cover(400, 400)
        ->toWebp()
        ->storePublicly('avatars', 'public');

    // ...
});
```

Alternatively, you may create an image instance from an `LaraGram\Http\UploadedFile` instance using the `fromUpload` method:

```php
use LaraGram\Support\Facades\Image;

$image = Image::fromUpload($request->file('avatar'));
```

When an image is created from an uploaded file, you may retrieve the underlying uploaded file using the `file` method:

```php
$file = $image->file();
```

<a name="storage-files"></a>
### Storage Files

You may create an image instance from a file stored on one of your application's [filesystem disks](/v4/filesystem) using the `fromStorage` method. The first argument is the path to the file, while the second argument is the disk name:

```php
use LaraGram\Support\Facades\Image;

$image = Image::fromStorage('avatars/photo.jpg', disk: 'public');
```

You may also create image instances directly from a filesystem disk instance using the `image` method:

```php
use LaraGram\Support\Facades\Storage;

$image = Storage::disk('public')->image('avatars/photo.jpg');
```

<a name="other-sources"></a>
### Other Sources

The `Image` facade also includes methods for creating image instances from raw bytes, streams, local file paths, remote URLs, and Base64 encoded strings:

```php
use LaraGram\Support\Facades\Image;

$image = Image::fromBytes($contents);
$image = Image::fromStream($stream);
$image = Image::fromBase64($base64);
$image = Image::fromPath(storage_path('app/avatars/photo.jpg'));
$image = Image::fromUrl('https://example.com/photo.jpg');
```

<a name="manipulating-images"></a>
## Manipulating Images

Image instances are immutable. Each manipulation method returns a new image instance with the transformation appended to its processing pipeline, allowing methods to be chained fluently:

```php
$image = $request->image()
    ->orient()
    ->cover(400, 400)
    ->sharpen(10);
```

Transformations are processed in the order they are added to the image pipeline and the image is only encoded once at the end.

<a name="resizing-images"></a>
### Resizing Images

The `resize` method resizes an image to the given dimensions. You may provide both a width and height, or provide only one dimension using named arguments:

```php
$image = $image->resize(800, 600);
$image = $image->resize(width: 800);
$image = $image->resize(height: 600);
```

The `scale` method proportionally scales an image down so that it fits within the given dimensions. This method will never increase the size of an image:

```php
$image = $image->scale(800, 600);
$image = $image->scale(width: 800);
$image = $image->scale(height: 600);
```

The `cover` method resizes and crops an image to completely cover the given dimensions:

```php
$image = $image->cover(400, 400);
```

The `contain` method resizes an image to fit within the given dimensions while preserving the entire image. If necessary, empty space will be filled using the optional background color:

```php
$image = $image->contain(400, 400);
$image = $image->contain(400, 400, '#ffffff');
$image = $image->contain(400, 400, 'dominant');
```

You may specify `dominant` as the background color to fill empty space using the image's dominant color.

You may crop an image using the `crop` method. The first two arguments are the desired width and height, and the optional third and fourth arguments specify the crop's `x` and `y` coordinates:

```php
$image = $image->crop(300, 200);
$image = $image->crop(300, 200, x: 50, y: 25);
```

<a name="other-transformations"></a>
### Other Transformations

LaraGram also provides a variety of additional image transformation methods:

```php
$image = $image->orient();
$image = $image->rotate(90);
$image = $image->rotate(90, '#ffffff');
$image = $image->rotate(90, 'dominant');
$image = $image->blur(5);
$image = $image->grayscale();
$image = $image->sharpen(10);
$image = $image->flipVertically();
$image = $image->flipHorizontally();
```

The `orient` method rotates the image according to its EXIF orientation data. The `rotate` method rotates the image clockwise by the given angle and accepts an optional background color. The `blur` and `sharpen` methods accept values between `0` and `100`. The `flip` and `flop` methods are aliases of `flipVertically` and `flipHorizontally`.

<a name="conditional-transformations"></a>
#### Conditional Transformations

Image instances support LaraGram's `Conditionable` trait, allowing you to conditionally apply transformations using the `when` and `unless` methods:

```php
$image = $request->image('avatar')
    ->when($request->boolean('crop'), fn ($image) => $image->cover(400, 400))
    ->unless($request->boolean('preserve_format'), fn ($image) => $image->toWebp());
```

<a name="encoding-images"></a>
## Encoding Images

By default, processed images are encoded using their original format. However, you may convert the image to another supported format before retrieving or storing it:

```php
$image = $image->toWebp();
$image = $image->toJpg();
$image = $image->toJpeg();
$image = $image->toPng();
$image = $image->toGif();
$image = $image->toAvif();
$image = $image->toHeic();
$image = $image->toBmp();
```

You may use the `quality` method to set the output quality. The quality will be clamped between `1` and `100`:

```php
$image = $image->toWebp()->quality(80);
```

The `optimize` method is a convenient shortcut for converting the image to a given format and setting its quality. By default, images are optimized as WebP images with a quality of `70`:

```php
$image = $image->optimize();

$image = $image->optimize(format: 'jpg', quality: 85);
```

You may retrieve the processed image contents as a string of bytes, base64 encoded string, or data URI:

```php
$bytes = $image->toBytes();
$base64 = $image->toBase64();
$dataUri = $image->toDataUri();
```

An image instance may also be cast to a string to retrieve a data URI:

```php
$dataUri = (string) $image;
```

<a name="sending-images-to-telegram"></a>
## Sending Images to Telegram

The `toInputFile` method processes the image and wraps it in a `CURLStringFile` instance that may be passed to any Bot API method accepting an uploaded file, such as `sendPhoto` or `sendDocument`. The image is uploaded directly from memory, so no temporary file is written to disk:

```php
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

Bot::onPhoto(function (Request $request) {
    $photo = $request->image()
        ->grayscale()
        ->scale(1280, 1280)
        ->toJpg()
        ->quality(85);

    $request->sendPhoto(chat()->id, $photo->toInputFile());
});
```

By default, the uploaded file is given a random name with the correct extension. You may pass a custom file name as the first argument:

```php
$request->sendDocument(chat()->id, $image->toInputFile('report.png'));
```

> [!NOTE]
> Telegram compresses photos sent with `sendPhoto`. To deliver the processed image without any additional compression, send it using `sendDocument` instead.

<a name="returning-images-from-web-routes"></a>
### Returning Images From Web Routes

Image instances implement the `Responsable` contract, so they may be returned directly from your web routes and controllers. LaraGram will respond with the processed image bytes and the correct `Content-Type` header:

```php
use App\Models\User;
use LaraGram\Support\Facades\Storage;

Route::get('/avatars/{user}', function (User $user) {
    return Storage::disk('public')->image($user->avatar_path)->cover(128, 128)->toWebp();
});
```

<a name="storing-images"></a>
## Storing Images

The `store` method stores the processed image on one of your application's filesystem disks. Like uploaded files, LaraGram will generate a unique filename and return the stored path. The second argument may be used to specify the disk:

```php
$path = $request->image()
    ->cover(400, 400)
    ->store(path: 'avatars');

$path = $request->image()
    ->cover(400, 400)
    ->store(path: 'avatars', disk: 's3');
```

You may use the `storeAs` method to specify the stored filename:

```php
$path = $request->image()
    ->cover(400, 400)
    ->storeAs(path: 'avatars', name: 'avatar.jpg', disk: 'public');
```

The `storePublicly` and `storePubliclyAs` methods store the image with `public` visibility:

```php
$path = $request->image()
    ->cover(400, 400)
    ->storePublicly(path: 'avatars', disk: 'public');

$path = $request->image()
    ->cover(400, 400)
    ->storePubliclyAs(path: 'avatars', name: 'avatar.webp', disk: 'public');
```

If the image could not be stored, the storage methods return `false`.

The `hashName` method returns the generated filename used by the `store` methods, including the extension of the processed image:

```php
$name = $image->hashName();
$path = $image->hashName('avatars');
```

> [!WARNING]
> Image instances cannot be serialized. When processing an image on a queued job, store the original image first and pass its path to the job instead of the image instance.

<a name="inspecting-images"></a>
## Inspecting Images

You may retrieve the image's MIME type, extension, dimensions, width, height, and dominant color using the following methods:

```php
$mimeType = $image->mimeType();
$extension = $image->extension();

[$width, $height] = $image->dimensions();
$width = $image->width();
$height = $image->height();

$dominantColor = $image->dominantColor();
```

These methods operate on the processed image. For example, calling `width` after `cover(400, 400)` will return `400`.

<a name="image-drivers"></a>
## Image Drivers

By default, images are processed using your application's default image driver. You may choose the driver for a specific image using the `using`, `usingGd`, or `usingImagick` methods:

```php
$image = $image->using('imagick');

$image = $image->usingGd();
$image = $image->usingImagick();
```

<a name="custom-image-drivers"></a>
### Custom Image Drivers

LaraGram's image manager extends LaraGram's base `LaraGram\Support\Manager` class. This means you may register custom image drivers using the `extend` method available on the image manager and `Image` facade.

Custom image drivers should implement the `LaraGram\Contracts\Image\Driver` interface. The `process` method receives the original image contents and the ordered `LaraGram\Image\ImagePipeline` that should be applied to the image, and should return the processed image bytes. The `dimensions` method should return the image's width and height, while the `dominantColor` method should return the image's average color as a hex string:

```php
<?php

namespace App\Images;

use LaraGram\Contracts\Image\Driver;
use LaraGram\Image\ImagePipeline;

class VipsDriver implements Driver
{
    /**
     * Process the given image contents with the specified pipeline.
     */
    public function process(string $contents, ImagePipeline $pipeline): string
    {
        // Apply the pipeline's transformations and output options...

        return $contents;
    }

    /**
     * Get the dimensions of the given image contents.
     */
    public function dimensions(string $contents): array
    {
        // Read the image's width and height...

        return [0, 0];
    }

    /**
     * Get the dominant (average) color of the image as a hex string.
     */
    public function dominantColor(string $contents): string
    {
        // Calculate the image's average color...

        return '#000000';
    }

    /**
     * Register a transformation handler.
     */
    public function transformUsing(string $transformation, callable $callback): static
    {
        // Store the handler so it may be applied while processing the pipeline...

        return $this;
    }
}
```

> [!NOTE]
> To better understand how to implement a custom image driver, you may review the framework's built-in `LaraGram\Image\Drivers\InterventionDriver` class.

Once you have implemented your custom driver, you may register it using the `Image` facade's `extend` method. Typically, this should be done in the `boot` method of a service provider:

```php
use App\Images\VipsDriver;
use LaraGram\Contracts\Foundation\Application;
use LaraGram\Support\Facades\Image;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Image::extend('vips', function (Application $app) {
        return new VipsDriver;
    });
}
```

After registering the driver, you may use it for a specific image using the `using` method:

```php
$image = $request->image()
    ->using('vips')
    ->cover(400, 400);
```

You may also configure a custom driver as your application's default image driver using the `default` option in your application's `config/images.php` configuration file or the `IMAGE_DRIVER` environment variable:

```ini
IMAGE_DRIVER=vips
```

<a name="custom-transformations"></a>
### Custom Transformations

Applications and packages may define custom transformations by creating a class that implements the `LaraGram\Contracts\Image\Transformation` contract. Custom transformations can then be added to an image pipeline using the `transform` method:

```php
<?php

namespace App\Images\Transformations;

use LaraGram\Contracts\Image\Transformation;

class Pixelate implements Transformation
{
    public function __construct(
        public readonly int $size,
    ) {
        //
    }
}
```

Next, register a handler for the transformation and driver using the `Image` facade's `transformUsing` method. The handler of the built-in `gd` and `imagick` drivers receives an `Intervention\Image\Interfaces\ImageInterface` instance, giving you access to the full Intervention Image API. Typically, this should be done in the `boot` method of a service provider:

```php
use App\Images\Transformations\Pixelate;
use Intervention\Image\Interfaces\ImageInterface;
use LaraGram\Support\Facades\Image;

Image::transformUsing('gd', Pixelate::class, function (ImageInterface $image, Pixelate $transformation) {
    return $image->pixelate($transformation->size);
});
```

Once the transformation handler has been registered, you may apply the transformation to an image:

```php
use App\Images\Transformations\Pixelate;

$image = $request->image()
    ->transform(new Pixelate(12))
    ->store('avatars');
```

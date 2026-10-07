# File Storage

<a name="introduction"></a>
## Introduction

LaraGram provides a powerful filesystem abstraction with drivers for working with local filesystems, FTP, SFTP, and Amazon S3 compatible services. Even better, it's amazingly simple to switch between these storage options between your local development machine and production server as the API remains the same for each system.

<a name="configuration"></a>
## Configuration

LaraGram's filesystem configuration file is located at `config/filesystems.php`. Within this file, you may configure all of your filesystem "disks". Each disk represents a particular storage driver and storage location. Example configurations for each supported driver are included in the configuration file so you can modify the configuration to reflect your storage preferences and credentials.

The `local` driver interacts with files stored locally on the server running the LaraGram application.

> [!NOTE]
> You may configure as many disks as you like and may even have multiple disks that use the same driver.

<a name="the-local-driver"></a>
### The Local Driver

When using the `local` driver, all file operations are relative to the `root` directory defined in your `filesystems` configuration file. By default, this value is set to the `storage/app/private` directory. Therefore, the following method would write to `storage/app/private/example.txt`:

```php
use LaraGram\Support\Facades\Storage;

Storage::disk('local')->put('example.txt', 'Contents');
```

<a name="the-public-disk"></a>
### The Public Disk

The `public` disk included in your application's `filesystems` configuration file is intended for files that are going to be publicly accessible. By default, the `public` disk uses the `local` driver and stores its files in `storage/app/public`.

If your `public` disk uses the `local` driver and you want to make these files accessible from the web, you should create a symbolic link from source directory `storage/app/public` to target directory `public/storage`:

To create the symbolic link, you may use the `storage:link` Commander command:

```shell
php laragram storage:link
```

You may configure additional symbolic links in your `filesystems` configuration file. Each of the configured links will be created when you run the `storage:link` command:

```php
'links' => [
    public_path('storage') => storage_path('app/public'),
    public_path('images') => storage_path('app/images'),
],
```

The `storage:unlink` command may be used to destroy your configured symbolic links:

```shell
php laragram storage:unlink
```

<a name="driver-prerequisites"></a>
### Driver Prerequisites

The `local`, `ftp` and `scoped` drivers work out of the box. Two drivers talk to an outside service through its own SDK, which must be installed before they may be used:

```shell
# Amazon S3 and S3 compatible services
composer require aws/aws-sdk-php

# SFTP
composer require league/flysystem-sftp-v3
```

<a name="s3-driver-configuration"></a>
#### S3 Driver Configuration

The S3 driver's configuration lives in your `config/filesystems.php` file, and is usually driven by environment variables:

```ini
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false
```

<a name="s3-compatible-filesystems"></a>
#### Amazon S3 Compatible Filesystems

The same driver talks to any S3 compatible service — MinIO, Cloudflare R2, DigitalOcean Spaces, Backblaze B2 — by setting the `endpoint` option alongside the usual credentials:

```ini
AWS_ENDPOINT=https://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

> [!NOTE]
> Services that do not support per-object visibility, such as Cloudflare R2, are detected and their visibility calls are skipped, so `url` and `temporaryUrl` keep working.

<a name="ftp-driver-configuration"></a>
#### FTP Driver Configuration

The FTP driver is built in, but has no entry in the default configuration file. Add one when you need it:

```php
'ftp' => [
    'driver' => 'ftp',
    'host' => env('FTP_HOST'),
    'username' => env('FTP_USERNAME'),
    'password' => env('FTP_PASSWORD'),

    // Optional FTP Settings...
    // 'port' => env('FTP_PORT', 21),
    // 'root' => env('FTP_ROOT'),
    // 'passive' => true,
    // 'ssl' => true,
    // 'timeout' => 30,
],
```

<a name="sftp-driver-configuration"></a>
#### SFTP Driver Configuration

```php
'sftp' => [
    'driver' => 'sftp',
    'host' => env('SFTP_HOST'),

    // Settings for basic authentication...
    'username' => env('SFTP_USERNAME'),
    'password' => env('SFTP_PASSWORD'),

    // Settings for SSH key based authentication with encryption password...
    'privateKey' => env('SFTP_PRIVATE_KEY'),
    'passphrase' => env('SFTP_PASSPHRASE'),

    // Settings for file / directory permissions...
    'visibility' => 'private', // `private` = 0600, `public` = 0644
    'directory_visibility' => 'private', // `private` = 0700, `public` = 0755

    // Optional SFTP Settings...
    // 'hostFingerprint' => env('SFTP_HOST_FINGERPRINT'),
    // 'maxTries' => 4,
    // 'port' => env('SFTP_PORT', 22),
    // 'root' => env('SFTP_ROOT'),
    // 'timeout' => 30,
    // 'useAgent' => true,
],
```

<a name="scoped-and-read-only-filesystems"></a>
### Scoped and Read-Only Filesystems

A **scoped** disk is another disk with a path prefix, so every operation is confined to that subdirectory. It is a convenient way to give a feature its own corner of a bucket without repeating the prefix:

```php
'invoices' => [
    'driver' => 'scoped',
    'disk' => 's3',
    'prefix' => 'invoices',
],
```

Any disk may also be made **read-only**, so the code that uses it cannot write by accident:

```php
'archive' => [
    'driver' => 'local',
    'root' => storage_path('app/archive'),
    'read-only' => true,
],
```

<a name="obtaining-disk-instances"></a>
## Obtaining Disk Instances

The `Storage` facade may be used to interact with any of your configured disks. For example, you may use the `put` method on the facade to store an avatar on the default disk. If you call methods on the `Storage` facade without first calling the `disk` method, the method will automatically be passed to the default disk:

```php
use LaraGram\Support\Facades\Storage;

Storage::put('avatars/1', $content);
```

If your application interacts with multiple disks, you may use the `disk` method on the `Storage` facade to work with files on a particular disk:

```php
Storage::disk('private')->put('avatars/1', $content);
```

<a name="on-demand-disks"></a>
### On-Demand Disks

Sometimes you may wish to create a disk at runtime using a given configuration without that configuration actually being present in your application's `filesystems` configuration file. To accomplish this, you may pass a configuration array to the `Storage` facade's `build` method:

```php
use LaraGram\Support\Facades\Storage;

$disk = Storage::build([
    'driver' => 'local',
    'root' => '/path/to/root',
]);

$disk->put('image.jpg', $content);
```

<a name="retrieving-files"></a>
## Retrieving Files

The `get` method may be used to retrieve the contents of a file. The raw string contents of the file will be returned by the method. Remember, all file paths should be specified relative to the disk's "root" location:

```php
$contents = Storage::get('file.jpg');
```

If the file you are retrieving contains JSON, you may use the `json` method to retrieve the file and decode its contents:

```php
$orders = Storage::json('orders.json');
```

The `exists` method may be used to determine if a file exists on the disk:

```php
if (Storage::disk('private')->exists('file.jpg')) {
    // ...
}
```

The `missing` method may be used to determine if a file is missing from the disk:

```php
if (Storage::disk('s3')->missing('file.jpg')) {
    // ...
}
```

<a name="file-metadata"></a>
### File Metadata

In addition to reading and writing files, LaraGram can also provide information about the files themselves. For example, the `size` method may be used to get the size of a file in bytes:

```php
use LaraGram\Support\Facades\Storage;

$size = Storage::size('file.jpg');
```

The `lastModified` method returns the UNIX timestamp of the last time the file was modified:

```php
$time = Storage::lastModified('file.jpg');
```

The MIME type of a given file may be obtained via the `mimeType` method:

```php
$mime = Storage::mimeType('file.jpg');
```

<a name="downloading-files"></a>
### Downloading Files

The `download` method generates a response that forces the user's browser to download the file at the given path. It accepts a file name as its second argument, which determines the file name the user downloading the file will see, and an array of HTTP headers as its third argument:

```php
return Storage::download('file.jpg');

return Storage::download('file.jpg', $name, $headers);
```

<a name="file-urls"></a>
### File URLs

The `url` method gets the URL of a file. For the `local` driver this prepends `/storage` to the path and returns a relative URL; for the `s3` driver the fully qualified remote URL is returned:

```php
use LaraGram\Support\Facades\Storage;

$url = Storage::url('file.jpg');
```

When using the `local` driver, files that should be publicly accessible must be placed in `storage/app/public` and reached through the symbolic link created by [`storage:link`](#the-public-disk).

> [!WARNING]
> Remember, the `url` method does not URL encode the path. For that reason, store file names that always produce valid URLs.

<a name="url-host-customization"></a>
#### URL Host Customization

To change the host of URLs generated by a disk, add or change the `url` option in the disk's configuration:

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
    'serve' => true,
    'throw' => false,
],
```

<a name="temporary-urls"></a>
### Temporary URLs

The `temporaryUrl` method creates a URL that expires, which is the right way to hand out a private file:

```php
use LaraGram\Support\Facades\Storage;

$url = Storage::temporaryUrl(
    'file.jpg', now()->addMinutes(5)
);
```

The `local` driver supports temporary URLs as long as the disk has `'serve' => true`, which the default `public` disk already has. Other drivers hand the request to the service they talk to; the S3 driver, for example, accepts additional request parameters:

```php
$url = Storage::temporaryUrl(
    'file.jpg',
    now()->addMinutes(5),
    [
        'ResponseContentType' => 'application/octet-stream',
        'ResponseContentDisposition' => 'attachment; filename=file2.jpg',
    ]
);
```

If you need to generate temporary URLs for a driver that does not support them, or build them in a way of your own, register a `buildTemporaryUrlsUsing` callback from the `boot` method of a [service provider](/v4/providers):

```php
use DateTimeInterface;
use LaraGram\Support\Facades\Storage;
use LaraGram\Support\Facades\URL;

public function boot(): void
{
    Storage::disk('local')->buildTemporaryUrlsUsing(
        function (string $path, DateTimeInterface $expiration, array $options) {
            return URL::temporarySignedRoute(
                'files.download',
                $expiration,
                array_merge($options, ['path' => $path])
            );
        }
    );
}
```

<a name="automatic-streaming"></a>
### Automatic Streaming

Streaming a file to the browser reduces memory usage significantly. The `serve` method builds that response for you, choosing the right content type and headers from the file itself:

```php
use LaraGram\Http\Request;
use LaraGram\Support\Facades\Storage;

Route::get('/file/{path}', function (Request $request, string $path) {
    return Storage::disk('local')->serve($request, $path);
})->where('path', '.*');
```

A disk may also decide how it serves files, which is useful when every file needs a header of its own:

```php
Storage::disk('local')->serveUsing(function (Request $request, string $path) {
    return Storage::disk('local')->response($path, headers: [
        'Cache-Control' => 'max-age=3600',
    ]);
});
```

<a name="storing-files"></a>
## Storing Files

The `put` method may be used to store file contents on a disk. You may also pass a PHP `resource` to the `put` method, which will use the underlying stream support. Remember, all file paths should be specified relative to the "root" location configured for the disk:

```php
use LaraGram\Support\Facades\Storage;

Storage::put('file.jpg', $contents);

Storage::put('file.jpg', $resource);
```

<a name="failed-writes"></a>
#### Failed Writes

If the `put` method (or other "write" operations) is unable to write the file to disk, `false` will be returned:

```php
if (! Storage::put('file.jpg', $contents)) {
    // The file could not be written to disk...
}
```

<a name="prepending-appending-to-files"></a>
### Prepending and Appending To Files

The `prepend` and `append` methods allow you to write to the beginning or end of a file:

```php
Storage::prepend('file.log', 'Prepended Text');

Storage::append('file.log', 'Appended Text');
```

<a name="copying-moving-files"></a>
### Copying and Moving Files

The `copy` method may be used to copy an existing file to a new location on the disk, while the `move` method may be used to rename or move an existing file to a new location:

```php
Storage::copy('old/file.jpg', 'new/file.jpg');

Storage::move('old/file.jpg', 'new/file.jpg');
```

<a name="file-uploads"></a>
### File Uploads

On the web side of your application, a file that was uploaded through a form is stored with the `store` method, which generates a unique id for the file name:

```php
use LaraGram\Http\Request;

Route::post('/avatar', function (Request $request) {
    $path = $request->file('avatar')->store('avatars');

    return $path;
});
```

The `storeAs` method names the file yourself, and both methods accept the disk as their last argument:

```php
$path = $request->file('avatar')->storeAs('avatars', $user->id);

$path = $request->file('avatar')->store('avatars', 's3');
```

The same may be done from the `Storage` facade with `putFile` and `putFileAs`, which accept an uploaded file or a `LaraGram\Http\File` instance:

```php
use LaraGram\Http\File;
use LaraGram\Support\Facades\Storage;

// Automatically generate a unique ID for the file name...
$path = Storage::putFile('photos', new File('/path/to/photo'));

// Manually specify a file name...
$path = Storage::putFileAs('photos', new File('/path/to/photo'), 'photo.jpg');
```

<a name="storing-telegram-files"></a>
#### Storing Files Sent to Your Bot

Files that arrive from Telegram are not HTTP uploads: they are referenced by a `file_id`, and LaraGram downloads them for you. Every [media file](/v4/requests#working-with-media-files) of an update may be written straight to a disk:

```php
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

Bot::onPhoto(function (Request $request) {
    // The last file of a photo bag is its largest size...
    $request->file()->last()->download('avatars/'.user()->id.'.jpg', 'public');
});
```

Inside a [conversation](/v4/conversations), an answer that holds media downloads the same way:

```php
$answers->get('avatar')->download('avatars/'.user()->id.'.jpg', 'public');
```

> [!NOTE]
> Telegram's Bot API only serves files up to 20 MB. For anything larger, download it over [MTProto](/v4/mtproto-media) instead.

<a name="image-manipulation"></a>
### Image Manipulation

Images may be resized, cropped and converted before they are stored, using LaraGram's [image manipulation](/v4/images) component:

```php
use LaraGram\Support\Facades\Image;

$path = Image::fromUpload($request->file('photo'))
    ->scale(width: 512)
    ->toWebp()
    ->quality(80)
    ->store(path: 'thumbnails', disk: 'public');
```

<a name="file-visibility"></a>
### File Visibility

In LaraGram, "visibility" is an abstraction of file permissions across multiple platforms. Files may either be declared `public` or `private`. When a file is declared `public`, you are indicating that the file should generally be accessible to others.

You can set the visibility when writing the file via the `put` method:

```php
use LaraGram\Support\Facades\Storage;

Storage::put('file.jpg', $contents, 'public');
```

If the file has already been stored, its visibility can be retrieved and set via the `getVisibility` and `setVisibility` methods:

```php
$visibility = Storage::getVisibility('file.jpg');

Storage::setVisibility('file.jpg', 'public');
```

When interacting with uploaded files, you may use the `storePublicly` and `storePubliclyAs` methods to store the uploaded file with `public` visibility:

```php
$path = $request->file('avatar')->storePublicly('avatars', 's3');

$path = $request->file('avatar')->storePubliclyAs(
    'avatars',
    $request->user()->id,
    's3'
);
```

<a name="deleting-files"></a>
## Deleting Files

The `delete` method accepts a single filename or an array of files to delete:

```php
use LaraGram\Support\Facades\Storage;

Storage::delete('file.jpg');

Storage::delete(['file.jpg', 'file2.jpg']);
```

If necessary, you may specify the disk that the file should be deleted from:

```php
use LaraGram\Support\Facades\Storage;

Storage::disk('private')->delete('path/file.jpg');
```

<a name="directories"></a>
## Directories

<a name="get-all-files-within-a-directory"></a>
#### Get All Files Within a Directory

The `files` method returns an array of all files within a given directory. If you would like to retrieve a list of all files within a given directory including subdirectories, you may use the `allFiles` method:

```php
use LaraGram\Support\Facades\Storage;

$files = Storage::files($directory);

$files = Storage::allFiles($directory);
```

<a name="get-all-directories-within-a-directory"></a>
#### Get All Directories Within a Directory

The `directories` method returns an array of all directories within a given directory. If you would like to retrieve a list of all directories within a given directory including subdirectories, you may use the `allDirectories` method:

```php
$directories = Storage::directories($directory);

$directories = Storage::allDirectories($directory);
```

<a name="create-a-directory"></a>
#### Create a Directory

The `makeDirectory` method will create the given directory, including any needed subdirectories:

```php
Storage::makeDirectory($directory);
```

<a name="delete-a-directory"></a>
#### Delete a Directory

Finally, the `deleteDirectory` method may be used to remove a directory and all of its files:

```php
Storage::deleteDirectory($directory);
```

<a name="custom-filesystems"></a>
## Custom Filesystems

A driver of your own is registered with the `Storage` facade's `extend` method, from the `boot` method of a [service provider](/v4/providers). The callback receives the application and the disk's configuration, and returns a `LaraGram\Filesystem\FilesystemAdapter` instance wrapping your adapter:

```php
use LaraGram\Contracts\Foundation\Application;
use LaraGram\Filesystem\FilesystemAdapter;
use LaraGram\Filesystem\Flysystem;
use LaraGram\Support\Facades\Storage;

public function boot(): void
{
    Storage::extend('dropbox', function (Application $app, array $config) {
        $adapter = new DropboxAdapter(/* ... */);

        return new FilesystemAdapter(
            new Flysystem($adapter, $config),
            $adapter,
            $config
        );
    });
}
```

The disk then uses the driver by name:

```php
'dropbox' => [
    'driver' => 'dropbox',
    // ...
],
```

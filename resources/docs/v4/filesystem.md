# File Storage

<a name="introduction"></a>
## Introduction

LaraGram provides a powerful filesystem abstraction thanks to the wonderful [Flysystem](https://github.com/thephpleague/flysystem) PHP package by Frank de Jonge. The LaraGram Flysystem integration provides simple drivers for working with local filesystems. Even better, it's amazingly simple to switch between these storage options between your local development machine and production server as the API remains the same for each system.

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

<a name="storing-files"></a>
## Storing Files

The `put` method may be used to store file contents on a disk. You may also pass a PHP `resource` to the `put` method, which will use Flysystem's underlying stream support. Remember, all file paths should be specified relative to the "root" location configured for the disk:

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

<a name="file-visibility"></a>
### File Visibility

In LaraGram's Flysystem integration, "visibility" is an abstraction of file permissions across multiple platforms. Files may either be declared `public` or `private`. When a file is declared `public`, you are indicating that the file should generally be accessible to others.

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

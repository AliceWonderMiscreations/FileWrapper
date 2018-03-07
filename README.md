\AWonderPHP\FileWrapper
=======================

An advanced PHP download wrapper

This class provides a PHP wrapper between the client requests and the files on
the server.

It handles most mime types intelligently and properly responds to client
requests for partial content as well as requests to see if the client cached
copy of the file is still valid.

Use of this class instead of letting the server just serve the file only makes
sense when the file is outside the server document root or when there are
conditions (such as age verification or other related things) that should be
checked before the file is served.

The class supports partial content requests and client cache validation. With
partial content requests, at this time only responds with the first partial
content range specified, it ignores the other parts.

The class also supports requests from the client asking if the version of the
file it has is up to date.

1. [Install](#install)
2. [Using the Class](#using-the-class)
3. [Example Usage](#example-usage)
4. [Catchable Exceptions](#catchable-exceptions)
5. [Extending the Class](#extending-the-class)
6. [Unit Testing](#unit-testing)


Install
-------

When there is a release, you can add this to a composer project via:

    "require": {
        "awonderphp/filewrapper": "^1.0"
    },
    
As long as your `composer.json` allows the [Packagist](https://packagist.org/)
repository, that should pull in this library when you run the command:

    composer install

### Manual Installation

For manual installation, there are four class libraries you need to have where
your auto-loader can find them:

1. `FileWrapper.php` -- This is the class library.
2. `InvalidArgumentException.php` -- An exception library.
3. `TypeErrorException.php` -- An exception library.
4. `NullPropertyException.php` -- An exception library.

All four libraries use the namespace `\AWonderPHP\FileWrapper`

### RPM Installation

I have started a project called
[PHP Composer Class Manager](https://github.com/AliceWonderMiscreations/php-ccm)
but it is not yet ready for deployment, and as of today (March 06 2018) it will
likely be awhile.


Using the Class
---------------

The class constructor has one required argument and four optional arguments.
The required argument is first, the path on the filesystem to the file being
served. The most basic way to use this class:

    use \AWonderPHP\FileWrapper\FileWrapper as FileWrapper;
    $obj = new FileWrapper('/srv/whatever/foo.mp4`);

The parameters the constructor takes:

1. `$path` -- __Required__  
  The path on the filesystem to the file being served. Always a `string`.
2. `$request` -- __Optional__  
  The name of the requested file that the client will see. This only matters if
  it is different than the name of the file on the filesystem *and* the file is
  being served as an attachment for the client to save to its local filesystem.
  Set it to `null` to just use the name of the file in `$path`, otherwise set
  it to a `string`. Default value is `null`.
3. `$mime` -- __Optional__  
  The MIME type the file should be served with. The class will attempt to sniff
  the correct MIME type if set to `null` but it is better to explicitly specify
  the MIME type. Use a `string` to specify a MIME type. To tell the class
  detect the mime type, set to `null`.
4. `$maxage` -- __Optional__  
  How long the client should cache the file for. This parameter can either be
  an integer representing number of seconds, an integer representing the UNIX
  timestamp when you want the cache to expire, a string that can be parsed by
  the `strtotime()` command specifying when you want the cache to expire, or a
  `\DateInterval` object specifying how long the browser should cache it for.
  The default value is `604800` seconds, which is one week.
5. `$attachment` -- __Optional__  
  Whether a header should be sent telling the client to save the file. Boolean
  default to `false`.

There are two public functions available once you have instantiated the class:

### `$obj->setAllowOrigin($origin)`

In some cases you may have a need to set the `access-control-allow-origin`
header. This function allows you to set it. Note that if the class is serving
a font, it automatically sets that header to `*` unless you specify otherwise.

### `$obj->sendfile()`

This causes the file to be sent to the requesting client.


Example Usage
-------------

### Image File

This example serves an image file:

    use \AWonderPHP\FileWrapper\FileWrapper as FileWrapper;
    $obj = new FileWrapper('/srv/images/sexy.jpg');
    $obj->sendfile();
    exit();

The class will figure out the file is `image/jpeg` and serve the file to the
requesting client as such.

### Audio Download

This example uses all five parameters to serve an audio file that the client
save to disk:

    use \AWonderPHP\FileWrapper\FileWrapper as FileWrapper;
    $obj = new FileWrapper('/srv/media/549805.mka', 'teaseme.mka', 'audio/x-matroska', 0, true);
    $obj->sendfile();
    exit();

The file on the server filesystem is named `549804.mka` which is not a very
descriptive name, so we use the second argument to give a more descriptive
file name the end user will benefit from.

The MIME type is explicitly set to `audio/x-matroska` which is helpful to the
client knowing what type of file is being downloaded.

We set the seconds for caching the file to 0 since it is a download, though
honestly that can be set to `null` as the cache time is not applicable to file
transfer.

Finally, we use `true` as the last argument so that the server sends the right
header to trigger the client to save the file to disk rather than open it in
the browser window.


Catchable Exceptions
--------------------

There are three catchable exception classes that accompany this class.

### \AWonderPHP\FileWrapper\InvalidArgumentException

This exception class extends
[`\InvalidArgumentException`](https://php.net/manual/en/class.invalidargumentexception.php)
and is thrown when a parameter is of the correct type but does not make sense.

Currently it is only thrown when a negative `$maxage` parameter is used or
when the `$maxage` is set using a string that the core PHP `strtotime` function
is not able to parse.

### \AWonderPHP\FileWrapper\TypeErrorException

This exception class extends
[`\TypeError`](https://php.net/manual/en/class.typeerror.php) and is thrown
when a variable set as a parameter is of the wrong type.

### \AWonderPHP\FileWrapper\NullPropertyException

This exception class extends
[`\ErrorException`](https://php.net/manual/en/class.errorexception.php) and is
thrown when a class property is `NULL` that should not be.

This exception should never happen, if it happens it is due to a bug in the
class.


Extending the Class
-------------------

This class also contains some methods useful when dealing with text based
files, such as conversion of non-UTF8 charsets to UTF8, minification of
JavaScript/CSS files, and word-wrapping of plain text files. While those
methods are in this class, they are not enabled, extend the class to enable
them:

    class TextWrapper extends \AliceWonderMiscreations\Utilities\FileWrapper
    {
        public function __construct(string $path, $request = null, $mime = null, bool $minify = false)
        {
            $this->toUTF8 = true;
            $maxage = 604800;
            if ($minify) {
                $this->minify = true;
            }
            parent::__construct($path, $request, $mime, $maxage, false);
        }
    }

That extended class turns on conversion to UTF8 and gives the option to minify
the files served with it automatically.


Unit Testing
------------

I have not yet created any unit tests for this class.

My personal experience is that unit tests reveal bugs the developer did not
know existed before writing the unit tests. That means there probably are some
bugs in this class.

That being said, I have been using this class on
[Naughty.Audio](https://naughty.audio/) for JS/CSS minification, as a file
download wrapper, and for HTML5 media with partial content requests.

It “works for me”.

Yes, I do need to create actual unit tests.

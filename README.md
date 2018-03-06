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



README NOT FINISHED
===================

I will finish today or tomorrow. The sections below are the end, it is the
middle I have to write.


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

    class textwrapper extends \AliceWonderMiscreations\Utilities\FileWrapper
    {
        __construct($path, $request = null, $mime = null, $minify = false)
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
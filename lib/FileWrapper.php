<?php
declare(strict_types = 1);

/**
 * An advanced PHP download wrapper.
 *
 * This class provides a PHP wrapper between the client requests and the files on the server.
 *
 * It handles most mime types intelligently and properly responds to client requests for
 * partial content as well as requests to see if the client cached copy of the file is still
 * valid.
 *
 * @package AWonderPHP\FileWrapper
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/FileWrapper
 */
/*
 +-----------------------------------------------------------------------+
 |                                                                       |
 | Copyright (c) 2017-2018 Alice Wonder Miscreations                     |
 |  May be used under terms of MIT license                               |
 |                                                                       |
 | Dedicated to Empress Andi of www.phonesexprincessblog.com             |
 |                                                                       |
 +-----------------------------------------------------------------------+

*/

namespace AWonderPHP\FileWrapper;

/**
 * This class provides a PHP wrapper between the client requests and the files on the server.
 *
 * It handles most mime types intelligently and properly responds to client requests for
 * partial content as well as requests to see if the client cached copy of the file is still
 * valid.
 */
class FileWrapper
{
    /**
     * When sent to true, an internal error header will be sent.
     *
     * @var bool
     */
    protected $internalError = false;

    /**
     * Array of known valid MIME types.
     *
     * If one of these mime types is specified to the constructor, the class will assume it
     * to be valid and will not waste time trying to detect the mime type.
     *
     * If extending this class and adding to the array, have the constructor of your class
     * add to the array *before* running the parent constructor.
     *
     * Mime types not listed here are supported, this list just lets the class know what it
     * can skip the mime sniff for.
     *
     * @var array
     */
    protected $validMime = array('application/x-bzip',
                                 'application/x-bzip2',
                                 'application/java-archive',
                                 'application/javascript',
                                 'application/json',
                                 'application/json-p',
                                 'application/xml',
                                 'application/msword',
                                 'application/ogg',
                                 'application/pdf',
                                 'application/vnd.ms-fontobject',
                                 'application/zip',
                                 'application/x-7z-compressed',
                                 'application/x-rar-compressed',
                                 'application/x-tar',
                                 'audio/3gpp',
                                 'audio/3gpp2',
                                 'audio/aiff',
                                 'audio/flac',
                                 'audio/mp4',
                                 'audio/mpeg',
                                 'audio/ogg',
                                 'audio/x-wav',
                                 'audio/webm',
                                 'audio/x-matroska',
                                 'font/otf',
                                 'font/sfnt',
                                 'font/ttf',
                                 'font/woff',
                                 'font/woff2',
                                 'image/bmp',
                                 'image/gif',
                                 'image/jpeg',
                                 'image/png',
                                 'image/svg+xml',
                                 'image/tiff',
                                 'image/webp',
                                 'text/css',
                                 'text/plain',
                                 'text/vtt',
                                 'video/3gpp',
                                 'video/3gpp2',
                                 'video/mp4',
                                 'video/ogg',
                                 'video/x-matroska',
                                 'video/webm');

    /**
     * MIME types we know are text files but do not start with "text/" or end with "+xml".
     *
     * @var array
     */
    protected $textMIME = array('application/javascript',
                                'application/json',
                                'application/json-p',
                                'application/xml');

    /**
     * The chunk size to read files in bytes.
     *
     * @var int
     */
    protected $chunksize = 1024;

    /**
     * This gets set to 'bytes' by checkFullFile() when the file is larger than the chunksize.
     *
     * @var string
     */
    protected $ranges = 'none';

    /**
     * Set to true by checkFullFile() if range request is bad.
     *
     * @var bool
     */
    protected $badrange = false;

    /**
     * The request headers sent by the client. Set by constructor.
     *
     * @var array
     */
    protected $REQHEADERS = array();

    /**
     * Full path on file system to static file being served. When null, 404 is sent.
     *
     * @var null|string
     */
    protected $path = null;

    /**
     * The name of the requested file as the user would see it. Only really
     * matters for files that are downloaded. Set by constructor.
     *
     * @var null|string
     */
    protected $request = null;

    /**
     * MIME type to send with the file. When null, the class will attempt to figure out the
     * correct MIME to send. Set by validMimeType() method.
     *
     * @var null|string
     */
    protected $mime = null;

    /**
     * If the class detects a text file, it is served differently.
     *
     * @var bool
     */
    protected $istext = false;

    /**
     * In some cases we need to specify access-control-allow-origin header.
     *
     * @var null|string
     */
    protected $allowOrigin = null;

    /**
     * When set to true by cacheCheck() a 304 Not Modified is sent
     *
     * @var bool
     */
    protected $cacheok = false;

    /**
     * Gets set to false by checkFullFile() for partial content response.
     *
     * @var bool
     */
    protected $fullfile = true;

    /**
     * Set by the setFileProperties() method
     *
     * @var null|int
     */
    protected $filesize = null;

    /**
     * First byte to read when sending content. Set by the checkFullFile() method.
     *
     * @var int
     */
    protected $start = 0;

    /**
     * Last byte to read when sending content. Set by the checkFullFile() method.
     *
     * @var int
     */
    protected $end = 0;

    /**
     * Total bytes to read when sending content. Set by the checkFullFile() method.
     *
     * @var int
     */
    protected $total = 0;

    /**
     * UNIX timestamp for last time file was modified. Set by setFileProperties() method.
     *
     * @var int
     */
    protected $timestamp = 0;

    /**
     * String representation of last time file was modified. Set by setFileProperties() method.
     *
     * @var string
     */
    protected $lastmod = 'Thu, 01 Jan 1970 00:00:00 GMT';

    /**
     * Unique identifier for the current version of the file. Set by the setFileProperties() method.
     *
     * @var null|string
     */
    protected $etag = null;

    /**
     * Whether or not to send as attachment. Set by constructor.
     *
     * @bool
     */
    protected $attachment = false;

    /**
     * Number of seconds the client should cache the file for. Set by the constructor.
     *
     * @var int
     */
    protected $maxage = 0;
  
// Text file specific - set these in an extended class

    /**
     * Should we convert non-utf8 to utf8? Set by extending class.
     *
     * @var bool
     */
    protected $toUTF8 = false;

    /**
     * Should we minify JS/CSS? Set by extending class.
     *
     * @var bool
     */
    protected $minify = false;

    /**
     * Charset list - set by extending class.
     *
     * @var string
     */
    protected $charsetlist = 'auto';

    /**
     * Character encoding. Set by the cleanSource() method.
     *
     * @var null|string
     */
    protected $charEnc='';
  
    /**
     * Does not seem to be used, will be removed if I can not find why it is here.
     *
     * @var null
     */
    protected $charset = null;
  
// Protected Methods.

    /**
     * Sends a 500 internal server error.
     *
     * @return void
     */
    protected function sendInternalError(): void
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        exit;
    }//end sendInternalError()

    
    /**
     * Set the request property
     *
     * @param string|null $request The name of file as user sees it, only matter for file
     *                             download.
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function setRequest($request): void
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        if (! is_null($request)) {
            if (! is_string($request)) {
                throw \AWonderPHP\FileWrapper\TypeErrorException::requestWrongType($request);
            }
            $request = trim(basename($request));
            if (strlen($request) === 0) {
                $request = null;
            }
        }
        if (is_null($request)) {
            $request = trim(basename($this->path));
        }
        $this->request = $request;
    }//end setRequest()


    /**
     * Sets the maxage property
     *
     * @param int|string|\DateInterval $maxage The maximum age the client should cache the
     *                                         file.
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function setMaxAge($maxage): void
    {
        $now = time();
        if (is_int($maxage)) {
            if ($maxage > $now) {
                $this->maxage = $maxage - $now;
                return;
            }
            if ($maxage >= 0) {
                $this->maxage = $maxage;
                return;
            }
            throw \AWonderPHP\FileWrapper\InvalidArgumentException::negativeMaxAge();
        }
        if ($maxage instanceof \DateInterval) {
            $dt = new \DateTime();
            $dt->add($maxage);
            $ts = $dt->getTimestamp();
            $seconds = $ts - $now;
            if ($seconds >= 0) {
                $this->maxage = $seconds;
                return;
            }
            throw \AWonderPHP\FileWrapper\InvalidArgumentException::negativeMaxAge();
        }
        if (! is_string($maxage)) {
            throw \AWonderPHP\FileWrapper\TypeErrorException::maxageWrongType($maxage);
        }
        if ($tstamp = strtotime($maxage, time())) {
            $seconds = $tstamp - $now;
            if ($seconds >= 0) {
                $this->maxage = $seconds;
                return;
            }
            throw \AWonderPHP\FileWrapper\InvalidArgumentException::negativeMaxAge();
        }
        throw \AWonderPHP\FileWrapper\InvalidArgumentException::invalidDateString();
    }//end setMaxAge()


    /**
     * Fixes common programmer typos and less than precise mime types from fileinfo.so.
     *
     * @param string $input The MIME type to be checked and possibly fixed.
     *
     * @return string Either the input MIME type or a correction of it.
     */
    protected function mimeTypoFix($input): string
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        $input = trim($input);
        $mime = $input;
        switch ($input) {
            case 'application/font-woff':
                $mime = 'font/woff';
                break;
            case 'audio/m4a':
                $mime = 'audio/mp4';
                break;
            case 'audio/matroska':
                $mime = 'audio/x-matroska';
                break;
            case 'audio/mp3':
                $mime = 'audio/mpeg';
                break;
            case 'audio/x-aiff':
                $mime = 'audio/aiff';
                break;
            case 'audio/wav':
                $mime = 'audio/x-wav';
                break;
            case 'audio/x-m4a':
                $mime = 'audio/mp4';
                break;
            case 'audio/x-matroska':
                $arr = explode('.', $this->path);
                $ext = strtolower(end($arr));
                switch ($ext) {
                    case 'weba':
                        $mime = 'audio/webm';
                        break;
                }
                break;
            case 'image/jpg':
                $mime = 'image/jpeg';
                break;
            case 'image/tif':
                $mime = 'image/tiff';
                break;
            case 'video/matroska':
                $mime = 'video/x-matroska';
                // test the extension too so no break
            case 'video/x-matroska':
                $arr = explode('.', $this->path);
                $ext = strtolower(end($arr));
                switch ($ext) {
                    case 'mka':
                        $mime = 'audio/x-matroska';
                        break;
                    case 'weba':
                        $mime = 'audio/webm';
                        break;
                    case 'webm':
                        $mime = 'video/webm';
                        break;
                    case 'webm2':
                        $mime = 'video/webm';
                        break;
                }
                break;
            case 'application/octet-stream':
                $arr = explode('.', $this->path);
                $ext = strtolower(end($arr));
                switch ($ext) {
                    case 'opus':
                        $mime = 'audio/ogg';
                        break;
                }
                break;
            case 'text/plain':
                $arr = explode('.', $this->path);
                $ext = strtolower(end($arr));
                switch ($ext) {
                    case 'js':
                        $mime = 'application/javascript';
                        break;
                    case 'css':
                        $mime = 'text/css';
                        break;
                    case 'vtt':
                        $mime = 'text/vtt';
                        break;
                }
                break;
        }
        return $mime;
    }//end mimeTypoFix()


    /**
     * Sets the MIME type the file will be served with. Does not compensate for all errors in
     * declared MIME types but will compensate for some.
     *
     * @param null|string $input A MIME type.
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function validMimeType($input): void
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        if (! is_null($input)) {
            if (! is_string($input)) {
                throw \AWonderPHP\FileWrapper\TypeErrorException::mimeWrongType($input);
            }
            $input = trim(strtolower($input));
            try {
                $input = $this->mimeTypoFix($input);
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            if (in_array($input, $this->validMime)) {
                $this->mime = $input;
                return;
            }
        }
        // do what we can
        if ($input == "application/octet-stream") {
            $input = null;
        }
        $error = false;
        if (! is_null($input)) {
            $arr = explode('/', $input);
            if (count($arr) != 2) {
                $error = true;
            } elseif (! in_array($arr[0], array('application',
                                                'audio',
                                                'font',
                                                'image',
                                                'multipart',
                                                'text',
                                                'video'))) {
                $error = true;
            }
            if ($error) {
                if (function_exists('finfo_open')) {
                    if ($finfo = finfo_open(FILEINFO_MIME_TYPE)) {
                        if ($mime = finfo_file($finfo, $this->path)) {
                            try {
                                $this->mime = $this->mimeTypoFix($mime);
                            } catch (\ErrorException $e) {
                                error_log($e->getMessage());
                                $this->internalError = true;
                            }
                            finfo_close($finfo);
                            return;
                        }
                        finfo_close($finfo);
                    }
                }
            }
        }
        if (is_null($input)) {
            $input = 'application/octet-stream';
        }
        $this->mime = $input;
    }//end validMimeType()


    /**
     * Attempts to detect if serving a text file.
     *
     * @return void
     */
    protected function textCheck(): void
    {
        if (is_null($this->mime)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('mime');
        }
        $test = substr($this->mime, 0, 5);
        if ($test === 'text/') {
            $this->istext = true;
            return;
        }
        $test = substr($this->mime, -4);
        if ($test === "+xml") {
            $this->istext = true;
            return;
        }
        if (in_array($this->mime, $this->textMIME)) {
            $this->istext = true;
        }
    }//end textCheck()


    /**
     * If serving a font, sets the allowOrigin to * which browsers need.
     *
     * You can use the PHP wrapper calling this class to send a 403 if the referer is not an
     * approved domain.
     *
     * @return void
     */
    protected function fontCheck(): void
    {
        if (is_null($this->mime)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('mime');
        }
        $test = substr($this->mime, 0, 5);
        if ($test == 'font/') {
            $this->allowOrigin = "*";
            return;
        }
        if (strcmp($this->mime, 'application/vnd.ms-fontobject') == 0) {
            $this->allowOrigin = "*";
        }
    }//end fontCheck()


    /**
     * Are we serving the full file or just part of it?
     *
     * @return void
     */
    protected function checkFullFile(): void
    {
        if (is_null($this->filesize)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('filesize');
        }
        $this->end = $this->filesize - 1;
        $this->total = $this->filesize;
        if ($this->istext) {
            return;
        }
        if ($this->filesize > $this->chunksize) {
            $this->ranges = 'bytes';
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if ($size_unit == 'bytes') {
                    $this->fullfile = false;
                    //below causes noise in log, so do w/ if then instead. Not using extra_ranges anyway
                    //list($range, $extra_ranges) = explode(',', $range_orig, 2);
                    $arr = explode(',', $range_orig, 2);
                    if (isset($arr[1])) {
                        $extra_ranges = $arr[1];
                    } else {
                        $extra_ranges = null;
                    }
                    $range = $arr[0];
                    list($start, $end) = explode('-', $range, 2);
                } else {
                    $this->badrange = true;
                }
                $end = (empty($end)) ? ($this->filesize - 1) : min(abs(intval($end)), ($this->filesize - 1));
                $start = (empty($start) || $end < abs(intval($start))) ? 0 : max(abs(intval($start)), 0);
                if ($start > 0 || $end < ($this->filesize - 1)) {
                    $this->start = $start;
                    $this->end = $end;
                    $this->total = $this->end - $this->start + 1;
                }
            }
        }
    }//end checkFullFile()


    /**
     * Sets class properties needed to generate a unique Etag and to serve the file.
     *
     * @return void
     */
    protected function setFileProperties(): void
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        date_default_timezone_set('UTC');
        $this->filesize = filesize($this->path);
        $this->timestamp = filemtime($this->path);
        $this->lastmod = preg_replace('/\+0000$/', 'GMT', date('r', $this->timestamp));
        $inode = fileinode($this->path);
        // The f4a24ef etc strings - my understand in that
        //  because of caching proxies, the Etag needs to be
        //  different for different types of transfer encoding
        //  (e.g. brotli, gzip, deflate) or content could be
        //  delivered to a client that the client can not
        //  decompress.
        // This class assumes only text files will potentially
        //  be further compressed when serving.
        $etagEnd = 'f4a24ef';
        if ($this->istext) {
            if (ini_get('zlib.output_compression')) {
                $accept = 'identity';
                if ($this->minify) {
                    $etagEnd = '3d';
                } else {
                    $etagEnd = '4c';
                }
                if (isset($this->REQHEADERS['accept-encoding'])) {
                    $T = trim(strtolower($this->REQHEADERS['accept-encoding']));
                    if (strpos($T, 'gzip') !== false) {
                        $accept = 'gzip';
                    } elseif (strpos($T, 'deflate') !== false) {
                        $accept = 'deflate';
                    }
                }
                switch ($accept) {
                    case 'gzip':
                        $etagEnd .= '7aa23';
                        break;
                    case 'deflate':
                        $etagEnd .= '98db4';
                        break;
                    default:
                        $etagEnd .= 'c41ca';
                }
            }
        }
        $this->etag = sprintf("%x-%x-%x-%s", $inode, $this->filesize, $this->timestamp, $etagEnd);
        try {
            $this->checkFullFile();
        } catch (\ErrorException $e) {
            error_log($e->getMessage());
            $this->internalError = true;
            return;
        }
    }//end setFileProperties()


    /**
     * Is the browser cached version okay?
     *
     * @return void
     */
    protected function cacheCheck()
    {
        if (is_null($this->etag)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('etag');
        }
        if (isset($this->REQHEADERS['if-none-match'])) {
            $reqETAG=trim($this->REQHEADERS['if-none-match'], '\'"');
            if (strcmp($reqETAG, $this->etag) == 0) {
                $this->cacheok=true;
            }
        } elseif (isset($this->REQHEADERS['if-modified-since'])) {
            $reqLMOD=strtotime(trim($this->REQHEADERS['if-modified-since']));
            if ($reqLMOD == $this->timestamp) {
                $this->cacheok=true;
            }
        }
    }//end cacheCheck()


    /**
     * Reads the file or portion of binary file from the filesystem and sends it to the
     * client.
     *
     * @return void
     */
    protected function readFromFilesystem(): void
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        $chunk = $this->chunksize;
        $sent = 0;
        $fp = fopen($this->path, 'rb');
        fseek($fp, $this->start);
        while (!feof($fp)) {
            if (($this->total - $sent) < $chunk) {
                $chunk = $this->total - $sent;
            }
            print(fread($fp, $chunk));
            flush();
            ob_flush();
            $sent = $sent + $chunk;
            if ($sent >= $this->total) {
                break;
            }
        }
        fclose($fp);
    }//end readFromFilesystem()


    /**
     * Send headers and then the file, for binary file.
     *
     * @return bool True on success, False on failure.
     */
    protected function sendContent(): bool
    {
        if (is_null($this->path)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->request)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->etag)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->mime)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->filesize)) {
            $this->sendInternalError();
            return false;
        }
        //make sure zlib output compression turned off
        ini_set("zlib.output_compression", "Off");
    
        if ($this->attachment) {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $this->request . '"');
        }
        if ($this->maxage == 0) {
            header('Cache-Control: must-revalidate');
        } else {
            header('Cache-Control: max-age=' . $this->maxage);
        }
        header('Last-Modified: ' . $this->lastmod);
        header('ETag: "' . $this->etag . '"');
        header('Accept-Ranges: ' . $this->ranges);
        if ($this->fullfile) {
            header('Content-Length: ' . $this->filesize);
        } else {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Length: ' . $this->total);
            header('Content-Range: bytes ' . $this->start . '-' . $this->end . '/' . $this->filesize);
        }
        if (! is_null($this->allowOrigin)) {
            header('access-control-allow-origin: ' . $this->allowOrigin);
        }
        header('Content-Type: ' . $this->mime);
        header_remove('X-Powered-By');
        try {
            $this->readFromFilesystem();
        } catch (\Error $e) {
            error_log($e->getMessage());
            $this->sendInternalError();
            return false;
        }
        return true;
    }//end sendContent()

  
// Text file specific Protected Methods

    /**
     * Cleans up line breaks and attempts to convert files to UTF8.
     *
     * By default this is turned off, extend class to use.
     *
     * @param string $content The text to be cleaned and converted.
     *
     * @return string The cleaned content.
     */
    protected function cleanSource($content): string
    {
        if (is_null($this->path)) {
            throw \AWonderPHP\FileWrapper\NullPropertyException::propertyIsNull('path');
        }
        //nuke BOM when we definitely have UTF8
        $bom = pack('H*', 'EFBBBF');
        //DOS to UNIX
        $content = str_replace("\r\n", "\n", $content);
        //Classic Mac to UNIX
        $content = str_replace("\r", "\n", $content);
        if (function_exists('mb_detect_encoding')) {
            if (mb_detect_encoding($content, 'UTF-8', true)) {
                $this->charEnc="UTF-8";
                $content = preg_replace("/^$bom/", '', $content);
            } elseif ($ENC = mb_detect_encoding($content, $this->charsetlist, true)) {
                $this->charEnc=$ENC;
                if (function_exists('iconv')) {
                    if ($new = iconv($ENC, 'UTF-8', $content)) {
                        $this->charEnc="UTF-8";
                        $content = preg_replace("/^$bom/", '', $new);
                    } else {
                      //conversion failed
                        error_log('Could not convert ' . $this->path . ' to UTF-8');
                    }
                }
            } else {
              //we could not detect character encoding
                error_log('Could not identify character encoding for ' . $this->path);
            }
        }
        return($content);
    }//end cleanSource()


    /**
     * Minify JavaScript. By default turned off, extend class to enable.
     *
     * @param string $content The content to be minified.
     *
     * @return string The minified content.
     */
    protected function jsminify($content): string
    {
        $JSqueeze = new \Patchwork\JSqueeze();
        return($JSqueeze->squeeze($content, true, false));
    }//end jsminify()


    /**
     * Minify CSS. By default turned off, extend class to enable.
     *
     * @author Manas Tungare  https://github.com/manastungare
     * @author Brenton String https://gist.github.com/brentonstrine
     *
     * @link https://gist.github.com/brentonstrine/5f56a24c7d34bb2d4655
     *
     * @param string $content The content to be minified.
     *
     * @return string The minified content.
     */
    protected function cssminify($content): string
    {
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        $content = str_replace(': ', ':', $content);
        $content = str_replace(array("\r\n", "\r", "\n", "\t"), '', $content);
        $content = preg_replace("/ {2,}/", ' ', $content);
        $content = str_replace(array('} '), '}', $content);
        $content = str_replace(array('{ '), '{', $content);
        $content = str_replace(array('; '), ';', $content);
        $content = str_replace(array(', '), ',', $content);
        $content = str_replace(array(' }'), '}', $content);
        $content = str_replace(array(' {'), '{', $content);
        $content = str_replace(array(' ;'), ';', $content);
        $content = str_replace(array(' ,'), ',', $content);
        return $content;
    }//end cssminify()


    /**
     * MultiByte safe Word-Wrap, needed because PHP does not have it native.
     *
     * @author Fosfor
     *
     * @link https://stackoverflow.com/users/615627/fosfor
     *
     * @param string $str   The content to be wrapped.
     * @param int    $width The line wrap width to use.
     * @param string $break The separator between lines.
     * @param bool   $cut   Whether to hard cut.
     *
     * @return string The Word-Wrapped content
     */
    protected function mbWordWrap($str, $width = 75, $break = "\n", $cut = false): string
    {
        $lines = explode($break, $str);
        foreach ($lines as &$line) {
            $line = rtrim($line);
            if (mb_strlen($line) <= $width) {
                continue;
            }
            $words = explode(' ', $line);
            $line = '';
            $actual = '';
            foreach ($words as $word) {
                if (mb_strlen($actual.$word) <= $width) {
                    $actual .= $word.' ';
                } else {
                    if ($actual != '') {
                        $line .= rtrim($actual).$break;
                    }
                    $actual = $word;
                    if ($cut) {
                        while (mb_strlen($actual) > $width) {
                            $line .= mb_substr($actual, 0, $width).$break;
                            $actual = mb_substr($actual, $width);
                        }
                    }
                    $actual .= ' ';
                }
            }
            $line .= trim($actual);
        }
        return implode($break, $lines);
    }//end mbWordWrap()


    /**
     * Word Wrap text files. Turned off by default, extend class to use.
     *
     * @param string $content The content to be word wrapped.
     *
     * @return string The word wrapped content.
     */
    protected function textwordwrap($content): string
    {
        $tmp = explode("\n", $content);
        $currmax = 0;
        foreach ($tmp as $line) {
            if (function_exists('mb_strlen')) {
                $lw = mb_strlen($line);
                if ($lw === false) {
                    $lw = strlen($line);
                }
            } else {
                $lw = strlen($line);
            }
            if ($lw > $currmax) {
                $currmax = $lw;
            }
        }
        if ($currmax > 120) {
            $n = count($tmp);
            for ($i=0; $i<$n; $i++) {
                if (function_exists('mbWordWrap')) {
                    $tmp[$i] = mbWordWrap($tmp[$i], 80, "\n", true);
                } elseif (function_exists('mb_strlen')) {
                    $tmp[$i] = $this->mbWordWrap($tmp[$i], 80, "\n", true);
                } else {
                    $tmp[$i] = wordwrap($tmp[$i], 80, "\n", true);
                }
            }
            $content = implode("\n", $tmp);
        }
        return $content;
    }//end textwordwrap()


    /**
     * Reads text file from filesystem and sends to browser.
     *
     * @return bool True on success, False on failure.
     */
    protected function getTextContent(): bool
    {
        if (is_null($this->path)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->mime)) {
            $this->sendInternalError();
            return false;
        }
        $content = file_get_contents($this->path);
        if ($this->toUTF8) {
            try {
                $content = $this->cleanSource($content);
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->sendInternalError();
                return false;
            }
        }
        if ($this->minify) {
            switch ($this->mime) {
                case "application/javascript":
                    $content=$this->jsminify($content);
                    break;
                case "text/css":
                    $content=$this->cssminify($content);
                    break;
                default:
                    $content=$this->textwordwrap($content);
                    break;
            }
        }
        $charset='';
        if (! is_null($this->charEnc)) {
            $charset='; charset=' . $this->charEnc;
        } elseif (function_exists('mb_detect_encoding')) {
            if ($ENC = mb_detect_encoding($content, $this->charsetlist, true)) {
                $charset='; charset=' . $ENC;
            }
        }
        header('Content-Type: ' . $this->mime . $charset);
        header_remove('X-Powered-By');
        print($content);
        return true;
    }//end getTextContent()


    /**
     * Serves text content.
     *
     * @return bool True on success, False on failure.
     */
    protected function serveText(): bool
    {
        // move this to a class property in future FIXME
        $vary = array();
        if (is_null($this->request)) {
            $this->sendInternalError();
            return false;
        }
        if (is_null($this->etag)) {
            $this->sendInternalError();
            return false;
        }
        if ($this->attachment) {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $this->request . '"');
        }
        if ($this->maxage == 0) {
            header('Cache-Control: must-revalidate');
        } else {
            header('Cache-Control: max-age=' . $this->maxage);
        }
        header('Last-Modified: ' . $this->lastmod);
        header('ETag: "' . $this->etag . '"');
        if (ini_get('zlib.output_compression')) {
            $vary[] = 'Accept-Encoding';
        }
        if (! is_null($this->allowOrigin)) {
            if ($this->allowOrigin !== '*') {
                $vary[] = 'Origin';
            }
        }
        if (count($vary) > 0) {
            $string = 'Vary: ' . implode(',', $vary);
            header($string);
        }
        if (! is_null($this->allowOrigin)) {
            header('access-control-allow-origin: ' . $this->allowOrigin);
        }
        return $this->getTextContent();
    }//end serveText()

  
// Public Methods

    /**
     * Set the access-control-allow-origin header.
     *
     * @param string $origin The origin to allow.
     *
     * @return void
     */
    public function setAllowOrigin($origin): void
    {
        $origin = trim($origin);
        // fixme - send catchable error if origin isn't valid
        //  possibly could check with filter_var
        if (strlen($origin) > 0) {
            $this->allowOrigin = $origin;
        }
    }//end setAllowOrigin()


    /**
     * Serve the file.
     *
     * @return bool True on success, False on failure.
     */
    public function sendfile(): bool
    {
        if ($this->internalError) {
            $this->sendInternalError();
            return false;
        }
        if ($this->cacheok) {
            header("HTTP/1.1 304 Not Modified");
            return true;
        }
        if (is_null($this->path)) {
            header("HTTP/1.0 404 Not Found");
            return false;
        }
        if ($this->badrange) {
            header("HTTP/1.1 416 Range Not Satisfiable");
            return false;
        }
        if ($this->istext) {
            return $this->serveText();
        }
        return $this->sendContent();
    }//end sendfile()


    /**
     * Constructor function.
     *
     * @param string                   $path The path on the filesystem to file being served.
     * @param null|string              $request The file being requested.
     * @param null|string              $mime The mime type of file being requested.
     * @param int|string|\DateInterval $maxage How long the file should be cached for.
     * @param bool                     $attachment Whether or not to serve file as attachment.
     */
    public function __construct(string $path, $request = null, $mime = null, $maxage = 604800, bool $attachment = false)
    {
        // validate input types
        
        
        if (file_exists($path)) {
            $this->REQHEADERS=array_change_key_case(getallheaders(), CASE_LOWER);
            $this->path = $path;
            try {
                $this->setRequest($request);
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            try {
                $this->validMimeType($mime);
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            try {
                $this->textCheck();
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            try {
                $this->fontCheck();
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            try {
                $this->setFileProperties();
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            try {
                $this->cacheCheck();
            } catch (\ErrorException $e) {
                error_log($e->getMessage());
                $this->internalError = true;
                return;
            }
            if ($attachment) {
                $this->attachment=true;
            } else {
                $this->setMaxAge($maxage);
            }
        }
    }//end __construct()
}//end class

?>
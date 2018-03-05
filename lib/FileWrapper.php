<?php

/*
 +-----------------------------------------------------------------------+
 |                                                                       |
 | Copyright (c) 2017-2018 Alice Wonder Miscreations                     |
 |  May be used under terms of MIT license                               |
 |                                                                       |
 | Dedicated to Empress Andi of www.phonesexprincessblog.com             |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Purpose: Allow server-side file wrappers to serve files from within   |
 |  PHP itself, regardless of whether file in document root or not.      |                                                          |
 |                                                                       |
 | Use of this class instead of letting the server just serve the file   |
 |  only makes sense when the file is outside the server document root   |
 |  or when there are conditions (such as age verification or other      |
 |  related things) that should be checked before the file is served.    |
 |                                                                       |
 | Supports partial content requests and client cache validation.        |
 | With partial content requests, at this time only responds with the    |
 |  first partial content range specified, it ignores the other parts.   |
 |                                                                       |
 | This class also contains some methods useful when dealing with text   |
 |  based files, such as conversion of non-UTF8 charsets to UTF8,        |
 |  minification of JavaScript/CSS files, and word-wrapping of plain     |
 |  text files. While those methods are in this class, they are not      |
 |  enabled, extend the class to enable them. For an example of how to   |
 |  extend the class to enable those features, please refer to:          |
 |                                                                       |
 |            https://naughty.audio/jscssWrapper2.phps                   |
 |                                                                       |
 | Minification of JavaScript requires the JSqueeze class that is        |
 |  available at https://github.com/nicolas-grekas/JSqueeze              |
 +-----------------------------------------------------------------------+
 | Class has three public functions:                                     |
 |   1) FileWrapper(the constructor - see options in below section)      |
 |   2) setAllowOrigin($origin) - call after the constructor if you need |
 |       to set a access-control-allow-origin header - note that if the  |
 |       file has a font mime type, this is automatically set to *       |
 |       resulting in the following header being sent by default:        |
 |         access-control-allow-origin: *                                |
 |   3) sendfile() - sends the file contents to the client.              |
 +-----------------------------------------------------------------------+
 | Constructor Function Options:                                         |
 |   1) path - required - full path on filesystem to file being wrapped  |
 |   2) request - optional - only matters when file is served as as a    |
 |       file transfer download. Derived from path if empty.             |
 |   3) mime type - optional - the class will try to figure out the      |
 |       mime type to use if empty. Class doesn't have to works as hard  |
 |       however if a correct mime type is specified.                    |
 |   4) maxage - age in seconds the browser should cache the file for.   |
 |       Ignored when file served as a file transfer download. Defaults  |
 |       to one week if not specified.                                   |
 |   5) attachment - whether or not the client should prompt the user to |
 |       save the file. Defaults to FALSE.                               |
 |                                                                       |
 | Image example:                                                        |
 |                                                                       |
 | use AliceWonderMiscreations\Utilities                                 |
 | $foo = new FileWrapper('/srv/images/sexy.jpg');                       |
 | $foo->sendfile();                                                     |
 | exit();                                                               |
 |                                                                       |
 | Audio Download Example w/ all five arguements specified.              |
 |                                                                       |
 | use AliceWonderMiscreations\Utilities
 | $foo = new FileWrapper('/srv/media/549805.mka', 'teaseme.mka', 'audio/x-matroska', 0, TRUE);
 | $foo->sendfile();
 | exit();                                                               |
 |                                                                       |
 | Conditional Example:                                                  |
 |                                                                       |
 | if($_SESSION['ageverified'] == 1) {                                   |
 |   $file="/srv/images/sexy.jpg";                                       |
 |   $cache=1209600                                                      |
 | } else {                                                              |
 |   $file="/srv/images/sexy-censored.jpg";                              |
 |   $cache=0                                                            |
 | }                                                                     |
 | use AliceWonderMiscreations\Utilities                                 |
 | $foo = new FileWrapper($file, '', 'image/jpeg', $cache);              |
 | $foo->sendfile();                                                     |
 | exit();                                                               |
 |                                                                       |
 | In the above example, if the user does not have the ageverified       |
 |  session variable set to 1, they are served a different version of    |
 |  image with instructions to the browser not to cache it. As soon as   |
 |  user verifies their age, they are sent the real version of the image |
 |  with browser instructions to cache it for two weeks.                 |
 +-----------------------------------------------------------------------+
*/

namespace AliceWonderMiscreations\Utilities;

class FileWrapper {
  // If one of these mime types is specified to the constructor, the class
  //  assumes it to be valid and doesn't need to waste any time trying to
  //  detect the mime type.
  // If extending class to add to array, add to this property *before*
  //  running the parent constructor or it won't have any effect
  // Mime types not listed are supported by the class, this just lets the
  //  class know it is a valid mime type and can skip checks.
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
                               
  // For mime types we know are text files but do not start with "text/"
  //  or end with "+xml"
  protected $textMIME = array('application/javascript',
                              'application/json',
                              'application/json-p',
                              'application/xml');

  // This class reads files in 1024 byte chunks. Seems to be pretty standard
  //  for most php scripts serving typical files.
  // It may not be ideal for large files.
  // Extend class to modify it.
  protected $chunksize = 1024;

  // Automatically set to bytes by checkFullFile() method when the file is
  //  larger than then the chunksize.
  protected $ranges = 'none';
  
  // Set to true by the checkFullFile() method if range request is bad
  protected $badrange = FALSE;

  // Array of headers sent by the client. Set by constructor function
  protected $REQHEADERS;
  
  // Full path on the filesystem to the static file being served by this
  //  wrapper class. When empty, a 404 is sent. Set by constructor function
  protected $path='';
  
  // The name of the requested file as the user would see it. Only really
  //  matters for files that are downloaded. Set by constructor function
  protected $request='';

  // mime type to send with the file. When empty, the class will attempt
  //  to figure it out, but better to specify a correct one to constructor.
  //  Set by the validMimeType() method
  protected $mime='';
  
  // If we detect it is a text file, we serve it differently
  protected $istext = FALSE;
  
  // In some cases we need to specify access-control-allow-origin header
  protected $allowOrigin = '';
  
  // When the client sends a header asking if the version of the file it has
  //  is current and it is, the cachecheck() method sets this to TRUE so a
  //  304 Not Modified header can be sent instead of the content.
  protected $cacheok = FALSE;

  // Whether or not we are sending the full file, or responding to a
  //  partial content request. Set by the checkFullFile() method.
  protected $fullfile = TRUE;
  
  // The size of the file. Set by the setFileProperties() method.
  protected $filesize;
  
  // First byte to read when sending content. Set by the checkFullFile()
  //  method.
  protected $start = 0;
  
  // Last byte to read when sending content. Set by the checkFullFile()
  //  method.
  protected $end = 0;
  
  // Total bytes to send. Set by the checkFullFile() method.
  protected $total = 0;
  
  // UNIX timestamp time stamp for the last time the file was modified.
  // Set by the setFileProperties() method.
  protected $timestamp;
  
  // String representation of above, used for header. Set by the
  //  setFileProperties() method.
  protected $lastmod;
  
  // Unique idenifier for the current version of the file.
  // Set by the setFileProperties() method.
  protected $etag;
  
  // Trigger the browser to save the file? Set by constructor function
  protected $attachment = FALSE;
  
  // Number of seconds the client should cache the file for. Set by the
  //  constructor function.
  protected $maxage = 0;
  
// Text file specific - set these in an extended class

  // Do we want to change non-utf8 to utf8?
  protected $toUTF8 = FALSE;

  // Do we want to minify JS/CSS
  protected $minify = FALSE;
  
  // charset list - can be overridden in extended class
  protected $charsetlist = 'auto';
  
  // character encoding
  protected $charEnc='';
  
  // character encoding
  protected $charset;
  
// Protected Methods.  
  
  // This function fixes common typos when specifying
  //  a mime type in php scripts. It also fixes less
  //  than precise mime types from the php fileinfo.so
  //  extension that sniffs mime types.
  // Replace this in an extended class for better accuracy
  //  specific to the file types you serve if what is
  //  here isn't complete enough for your needs.
  // Or just always specify CORRECT mime type to the
  //  constructor.
  protected function mimeTypoFix($input) {
    $input = trim($input);
    $mime = $input;
    switch($input) {
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
        switch($ext) {
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
        break;
      case 'video/x-matroska':
        $arr = explode('.', $this->path);
        $ext = strtolower(end($arr));
        switch($ext) {
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
        switch($ext) {
          case 'opus':
            $mime = 'audio/ogg';
            break;
        }
        break;
      case 'text/plain':
        $arr = explode('.', $this->path);
        $ext = strtolower(end($arr));
        switch($ext) {
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
  }
  
  // Sets the mime type the file will be served with.
  // Won't compensate for all errors in declared mime types
  //  but will compensate for some.
  protected function validMimeType($input) {
    $input = trim(strtolower($input));
    $input = $this->mimeTypoFix($input);
    if(in_array($input, $this->validMime)) {
      $this->mime = $input;
      return;
    }
    // do what we can
    if($input == "application/octet-stream") {
      $input='';
    }
    $error = FALSE;
    $arr = explode('/', $input);
    if(count($arr) != 2) {
      $error = TRUE;
    } elseif(! in_array($arr[0], array('application', 'audio', 'font', 'image', 'multipart', 'text', 'video'))) {
      $error = TRUE;
    }
    if($error) {
      if(function_exists('finfo_open')) {
        if ($finfo = finfo_open(FILEINFO_MIME_TYPE)) {
          if($mime = finfo_file($finfo, $this->path)) {
            $this->mime = $this->mimeTypoFix($mime);
            finfo_close($finfo);
            return;
          }
          finfo_close($finfo);
        }
      }
    }
    if(strlen($input) == 0) {
      $input = 'application/octet-stream';
    }
    $this->mime = $input;
  }
  
  protected function textCheck() {
    $test = substr($this->mime, 0, 5);
    if($test == 'text/') {
      $this->istext = TRUE;
      return;
    }
    $test = substr($this->mime, -4);
    if($test == "+xml") {
      $this->istext = TRUE;
      return;
    }
    if(in_array($this->mime, $this->textMIME)) {
      $this->istext = TRUE;
    }
  }
  
  // If we are a serving a font, default allowOrigin to *
  //  My recommendation is to leave the default and use
  //  php in the wrapper to check referer header. IFF
  //  the client sends a referer header and it is not
  //  in array of allowed, send a 403. If in list of
  //  allowed or empty, then send font with this default
  //  for the access-control-allow-origin
  protected function fontCheck() {
    $test = substr($this->mime, 0, 5);
    if($test == 'font/') {
      $this->allowOrigin = "*";
      return;
    }
    if(strcmp($this->mime, 'application/vnd.ms-fontobject') == 0) {
      $this->allowOrigin = "*";
    }
  }
  
  // are we serving the full file or just part of it?
  protected function checkFullFile() {
    $this->end = $this->filesize - 1;
    $this->total = $this->filesize;
    if($this->istext) {
      return;
    }
    if($this->filesize > $this->chunksize) {
      $this->ranges = 'bytes';
      if(isset($_SERVER['HTTP_RANGE'])) {
        list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if($size_unit == 'bytes') {
          $this->fullfile = FALSE;
          list($range, $extra_ranges) = explode(',', $range_orig, 2);
          list($start, $end) = explode('-', $range, 2);
        } else {
          $this->badrange = TRUE;
        }
        $end = (empty($end)) ? ($this->filesize - 1) : min(abs(intval($end)),($this->filesize - 1));
        $start = (empty($start) || $end < abs(intval($start))) ? 0 : max(abs(intval($start)),0);
        if($start > 0 || $end < ($this->filesize - 1)) {
          $this->start = $start;
          $this->end = $end;
          $this->total = $this->end - $this->start + 1;
        }
      }
    }
  }
  
  // sets class properties needed to generate unique Etag
  //  and to serve the file
  protected function setFileProperties() {
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
    if($this->istext) {
      if(ini_get('zlib.output_compression')) {
        $accept = 'identity';
        if($this->minify) {
          $etagEnd = '3d';
        } else {
          $etagEnd = '4c';
        }
        if(isset($this->REQHEADERS['accept-encoding'])) {
          $T = trim(strtolower($this->REQHEADERS['accept-encoding']));
          if(strpos($T, 'gzip') !== FALSE) {
            $accept = 'gzip';
          } elseif(strpos($T, 'deflate') !== FALSE) {
            $accept = 'deflate';
          }
        }
        switch($accept) {
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
    $this->checkFullFile();
  }
  
  // is the browser cached version okay?
  protected function cachecheck() {
    if(isset($this->REQHEADERS['if-none-match'])) {
      $reqETAG=trim($this->REQHEADERS['if-none-match'], '\'"');
      if (strcmp($reqETAG, $this->etag) == 0) {
        $this->cacheok=true;
      }
    } elseif(isset($this->REQHEADERS['if-modified-since'])) {
      $reqLMOD=strtotime(trim($this->REQHEADERS['if-modified-since']));
      if ($reqLMOD == $this->timestamp) {
        $this->cacheok=true;
      }
    }
  }
  
  // reads the file or portion of binary file from the filesystem
  //  and sends it to the browser
  protected function readFromFilesystem() {
    $chunk = $this->chunksize;
    $sent = 0;
    $fp = fopen($this->path, 'rb');
    fseek($fp, $this->start);
    while(!feof($fp)) {
      if(($this->total - $sent) < $chunk) {
        $chunk = $this->total - $sent;
      }
      print(fread($fp, $chunk));
      flush();
      ob_flush();
      $sent = $sent + $chunk;
      if($sent >= $this->total) {
        break;
      }
    }
    fclose($fp);
  }
  
  // Send headers and then the file, for binary file
  protected function sendContent() {
    //make sure zlib output compression turned off
    ini_set("zlib.output_compression", "Off");
    
    if($this->attachment) {
      header('Content-Description: File Transfer');
      header('Content-Disposition: attachment; filename="' . $this->request . '"');
    }
    if($this->maxage == 0) {
      header('Cache-Control: must-revalidate');
    } else {
      header('Cache-Control: max-age=' . $this->maxage);
    }
    header('Last-Modified: ' . $this->lastmod);
    header('ETag: "' . $this->etag . '"');
    header('Accept-Ranges: ' . $this->ranges);
    if($this->fullfile) {
      header('Content-Length: ' . $this->filesize);
    } else {
      header('HTTP/1.1 206 Partial Content');
      header('Content-Length: ' . $this->total);
      header('Content-Range: bytes ' . $this->start . '-' . $this->end . '/' . $this->filesize);
    }
    if(strlen($this->allowOrigin) > 0) {
      header('access-control-allow-origin: ' . $this->allowOrigin);
    }
    header('Content-Type: ' . $this->mime);
    header_remove('X-Powered-By');
    $this->readFromFilesystem();
  }
  
// Text file specific Protected Methods

  // this function cleans up line breaks and attempts to convert files to UTF8
  //  by default turned off
  protected function cleanSource($content) {
    //nuke BOM when we definitely have UTF8
    $bom = pack('H*','EFBBBF');
    //DOS to UNIX
    $content = str_replace("\r\n", "\n", $content);
    //Classic Mac to UNIX
    $content = str_replace("\r", "\n", $content);
    if(function_exists('mb_detect_encoding')) {
      if(mb_detect_encoding($content, 'UTF-8', TRUE)) {
        $this->charEnc="UTF-8";
        $content = preg_replace("/^$bom/", '', $content);
      } elseif($ENC = mb_detect_encoding($content, $this->charsetlist, TRUE)) {
        $this->charEnc=$ENC;
        if(function_exists('iconv')) {
          if($new = iconv($ENC, 'UTF-8', $content)) {
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
  }
  
  // minify JavaScript - by default turned off
  protected function jsminify($content) {
    $JSqueeze = new \Patchwork\JSqueeze();
    return($JSqueeze->squeeze($content, true, false));
  }
  
  // minify CSS - by default turned off
  //  https://gist.github.com/brentonstrine/5f56a24c7d34bb2d4655
  protected function cssminify($content) {
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
  }
  
  // mb word-wrap text files
  //  http://stackoverflow.com/questions/3825226/multi-byte-safe-wordwrap-function-for-utf-8
  //  until php gets an actual mb_wordwrap function
  protected function mb_wordwrap($str, $width = 75, $break = "\n", $cut = false) {
    $lines = explode($break, $str);
    foreach ($lines as &$line) {
      $line = rtrim($line);
      if (mb_strlen($line) <= $width)
        continue;
      $words = explode(' ', $line);
      $line = '';
      $actual = '';
      foreach ($words as $word) {
        if (mb_strlen($actual.$word) <= $width)
          $actual .= $word.' ';
        else {
          if ($actual != '')
            $line .= rtrim($actual).$break;
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
  }
  
  // word wrap text files - by default turned off
  protected function textwordwrap($content) {
    $tmp = explode("\n", $content);
    $currmax = 0;
    foreach($tmp as $line) {
      if(function_exists('mb_strlen')) {
        $lw = mb_strlen($line);
        if($lw === FALSE) {
          $lw = strlen($line);
        }
      } else {
        $lw = strlen($line);
      }
      if($lw > $currmax) {
        $currmax = $lw;
      }
    }
    if($currmax > 120) {
      $n = count($tmp);
      for($i=0; $i<$n; $i++) {
        if(function_exists('mb_wordwrap')) {
          $tmp[$i] = mb_wordwrap($tmp[$i], 80, "\n", TRUE);
        } elseif(function_exists('mb_strlen')) {
          $tmp[$i] = $this->mb_wordwrap($tmp[$i], 80, "\n", TRUE);
        } else {
          $tmp[$i] = wordwrap($tmp[$i], 80, "\n", TRUE);
        }
      }
      $content = implode("\n", $tmp);
    }
    return $content;
  }
  
  // reads text file from filesystem and sends to browser
  protected function getTextContent() {
    $content = file_get_contents($this->path);
    if($this->toUTF8) {
      $content = $this->cleanSource($content);
    }
    if($this->minify) {
      switch($this->mime) {
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
    if(strlen($this->charEnc) > 0) {
      $charset='; charset=' . $this->charEnc; 
    } elseif(function_exists('mb_detect_encoding')) {
      if($ENC = mb_detect_encoding($content, $this->charsetlist, TRUE)) {
        $charset='; charset=' . $ENC;
      }
    }
    header('Content-Type: ' . $this->mime . $charset);
    header_remove('X-Powered-By');
    print($content);
  }
  
  protected function serveText() {
    if($this->attachment) {
      header('Content-Description: File Transfer');
      header('Content-Disposition: attachment; filename="' . $this->request . '"');
    }
    if($this->maxage == 0) {
      header('Cache-Control: must-revalidate');
    } else {
      header('Cache-Control: max-age=' . $this->maxage);
    }
    header('Last-Modified: ' . $this->lastmod);
    header('ETag: "' . $this->etag . '"');
    if(ini_get('zlib.output_compression')) {
      header('Vary: Accept-Encoding');
    }
    if(strlen($this->allowOrigin) > 0) {
      header('access-control-allow-origin: ' . $this->allowOrigin);
    }
    $this->getTextContent();
  }
  
// Public Methods

  // set the access-control-allow-origin header
  public function setAllowOrigin($origin) {
    $origin = trim($origin);
    $this->allowOrigin = $origin;
  }
  
  // trigger serving of the file
  public function sendfile() {
    if($this->cacheok) {
      header("HTTP/1.1 304 Not Modified");
      return;
    }
    if(strlen($this->path) == 0) {
      header("HTTP/1.0 404 Not Found");
      return;
    }
    if($this->badrange) {
      header("HTTP/1.1 416 Range Not Satisfiable");
      return;
    }
    if($this->istext) {
      $this->serveText();
      return;
    }
    $this->sendContent();
  }
  
  // constructor function
  public function FileWrapper($path, $request='', $mime='', $maxage=604800, $attachment=FALSE) {
    if(file_exists($path)) {
      $this->REQHEADERS=array_change_key_case(getallheaders(), CASE_LOWER);
      $this->path = $path;
      $this->request = trim(basename($request));
      if(strlen($this->request) == 0) {
        $this->request = trim(basename($path));
      }
      $this->validMimeType($mime);
      $this->textCheck();
      $this->fontCheck();
      $this->setFileProperties();
      $this->cachecheck();
      if($attachment) {
        $this->attachment=TRUE;
      } else {
        if(is_numeric($maxage)) {
          $maxage = intval($maxage, 10);
          if($maxage > 0) {
            $this->maxage = $maxage;
          }
        }
      }
    }
  }
  //end of class
}

?>
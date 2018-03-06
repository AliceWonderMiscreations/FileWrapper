<?php
declare(strict_types = 1);

/**
 * Catchable exception for when the wrong variable type is used as a parameter
 *
 * @package AWonderPHP\FileWrapper
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCacheAPCu
 */
/*
 +-------------------------------------------------------+
 |                                                       |
 | Copyright (c) 2018 Alice Wonder Miscreations          |
 |  May be used under terms of MIT license               |
 |                                                       |
 +-------------------------------------------------------+
*/

namespace AWonderPHP\FileWrapper;

/**
 * Throws a \TypeError exception when the type of a parameter supplied does not match what was
 * expected.
 */
class TypeErrorException extends \TypeError
{
    /**
     * Exception message when maxage is wrong type
     *
     * @param mixed $var The parameter that was supplied
     *
     * @return \TypeError
     */
    public static function maxageWrongType($var)
    {
        $type = gettype($var);
        return new self(sprintf('The $maxage parameter must be an Integer, \DateInterval object, or a string. You supplied a %s.', $type));
    }

    /**
     * Exception message when MIME var is wrong type
     *
     * @param mixed $var The parameter that was supplied
     *
     * @return \TypeError
     */
    public static function mimeWrongType($var)
    {
        $type = gettype($var);
        return new self(sprintf('The $mime parameter must be NULL or a string. You supplied a %s.', $type));
    }

    /**
     * Exception message when request var is wrong type
     *
     * @param mixed $var The parameter that was supplied
     *
     * @return \TypeError
     */
    public static function requestWrongType($var)
    {
        $type = gettype($var);
        return new self(sprintf('The $request parameter must be NULL or a string. You supplied a %s.', $type));
    }
}
?>
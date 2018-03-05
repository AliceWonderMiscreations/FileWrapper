<?php
declare(strict_types = 1);

/**
 * Catchable exception for when in invalid argument is used as a parameter
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

class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * Exception message when supplied maxage is negative
     *
     * @return \InvalidArgumentException
     */
    public static function negativeMaxAge()
    {
        return new self(sprintf('The file maxage parameter can not be negative.'));
    }

    /**
     * Exception message when supplied maxage string can't be parsed by strtotime
     *
     * @return \InvalidArgumentException
     */
    public static function invalidDateString()
    {
        return new self(sprintf('The strtotime() command could not parse the string you supplied.'));
    }
}



















?>
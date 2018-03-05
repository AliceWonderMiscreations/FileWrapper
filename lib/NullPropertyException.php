<?php
declare(strict_types = 1);

/**
 * Catchable exception for when properties are not properly set
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
 * Throws a \ErrorException when a class property is null that should not be null.
 * This should never happen.
 */
class NullPropertyException extends \ErrorException
{
    /**
     * Error message when a class property is null.
     *
     * @param string $property The property that is null
     *
     * @return \ErrorException
     */
    public static function propertyIsNull(string $property)
    {
        return new self(sprintf('The %s property is null. This should not have happened.', $property));
    }
}
?>
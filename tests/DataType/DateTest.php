<?php

namespace IXR\tests\DataType;

use IXR\DataType\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{

    /**
     * @return array
     * @see testParseIso
     */
    function provideDates()
    {
        return [
            // full datetime, different formats
            ['2010-08-17T09:23:14', 1282036994],
            ['20100817T09:23:14', 1282036994],
            ['2010-08-17 09:23:14', 1282036994],
            ['20100817 09:23:14', 1282036994],
            ['2010-08-17T09:23:14Z', 1282036994],
            ['20100817T09:23:14Z', 1282036994],

            // with timezone
            ['2010-08-17 09:23:14+0000', 1282036994],
            ['2010-08-17 09:23:14+00:00', 1282036994],
            ['2010-08-17 12:23:14+03:00', 1282036994],

            // no seconds
            ['2010-08-17T09:23', 1282036980],
            ['20100817T09:23', 1282036980],

            // no time
            ['2010-08-17', 1282003200],
            [1282036980, 1282036980],
//            ['20100817', 1282003200], #this will NOT be parsed, but is assumed to be timestamp
        ];
    }

    /**
     * @dataProvider provideDates
     * @param mixed $input
     * @param int $expect
     */
    function testParseIso($input, $expect)
    {
        $dt = new Date($input);
        $this->assertEquals($expect, $dt->getTimeStamp());
    }

}

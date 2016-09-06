<?php

namespace IXR\DataType;

/**
 * IXR_Date
 *
 * @package IXR
 * @since 1.5.0
 */
class Date
{
    /** @var \DateTime */
    private $dateTime;

    public function __construct($time)
    {
        // $time can be a PHP timestamp or an ISO one
        if (is_numeric($time)) {
            $this->parseTimestamp($time);
        } else {
            $this->parseIso($time);
        }
    }

    private function parseTimestamp($timestamp)
    {
        $date = new \DateTime();
        $this->dateTime = $date->setTimestamp($timestamp);
    }

    private function parseIso($iso)
    {
        $this->dateTime = \DateTime::createFromFormat(\DateTime::ATOM, $iso);
    }

    public function getIso()
    {
        return $this->dateTime->format(\DateTime::ATOM);
    }

    public function getXml()
    {
        return '<dateTime.iso8601>' . $this->getIso() . '</dateTime.iso8601>';
    }

    public function getTimestamp()
    {
        return (int)$this->dateTime->format('U');
    }
}

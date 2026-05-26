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
    private \DateTime $dateTime;

    public function __construct(int|string $time)
    {
        // $time can be a PHP timestamp or an ISO one
        if (\is_numeric($time)) {
            $this->parseTimestamp((int) $time);
        } else {
            $this->parseIso($time);
        }
    }

    private function parseTimestamp(int $timestamp): void
    {
        $date = new \DateTime();
        $this->dateTime = $date->setTimestamp($timestamp);
    }

    /**
     * Parses more or less complete iso dates and much more, if no timezone given assumes UTC
     *
     * @throws \Exception when no valid date is given
     */
    protected function parseIso(string $iso): void
    {
        $this->dateTime = new \DateTime($iso, new \DateTimeZone('UTC'));
    }

    public function getIso(): string
    {
        return $this->dateTime->format(\DateTime::ATOM);
    }

    public function getXml(): string
    {
        return '<dateTime.iso8601>' . $this->getIso() . '</dateTime.iso8601>';
    }

    public function getTimestamp(): int
    {
        return (int)$this->dateTime->format('U');
    }
}

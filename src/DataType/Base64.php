<?php
namespace IXR\DataType;

/**
 * IXR_Base64
 *
 * @package IXR
 * @since 1.5.0
 */
readonly class Base64
{
    public function __construct(private mixed $data)
    {
    }

    public function getXml(): string
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}

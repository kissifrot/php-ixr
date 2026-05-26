<?php
namespace IXR\DataType;


class Value
{
    public function __construct(private mixed $data, private ?string $type = null)
    {
        $this->type = $type ?? $this->calculateType();
        if ($type === 'struct') {
            // Turn all the values in the array in to new IXR_Value objects
            foreach ($this->data as $key => $value) {
                $this->data[$key] = new Value($value);
            }
            return;
        }
        if ($type === 'array') {
            for ($i = 0, $j = \count($this->data); $i < $j; $i++) {
                $this->data[$i] = new Value($this->data[$i]);
            }
        }
    }

    public function calculateType(): string
    {
        if ($this->data === true || $this->data === false) {
            return 'boolean';
        }
        if (\is_integer($this->data)) {
            return 'int';
        }
        if (\is_double($this->data)) {
            return 'double';
        }

        // Deal with IXR object types base64 and date
        if (\is_object($this->data) && $this->data instanceof Date) {
            return 'date';
        }
        if (\is_object($this->data) && $this->data instanceof Base64) {
            return 'base64';
        }

        // If it is a normal PHP object convert it in to a struct
        if (\is_object($this->data)) {
            $this->data = get_object_vars($this->data);
            return 'struct';
        }
        if (!\is_array($this->data)) {
            return 'string';
        }

        // We have an array - is it an array or a struct?
        if ($this->isStruct($this->data)) {
            return 'struct';
        } else {
            return 'array';
        }
    }

    public function getXml(): false|string
    {
        // Return XML for this value
        switch ($this->type) {
            case 'boolean':
                return '<boolean>' . (((bool)$this->data) ? '1' : '0') . '</boolean>';
            case 'int':
                return '<int>' . $this->data . '</int>';
            case 'double':
                return '<double>' . $this->data . '</double>';
            case 'string':
                return '<string>' . htmlspecialchars($this->data) . '</string>';
            case 'array':
                $return = '<array><data>' . "\n";
                foreach ($this->data as $item) {
                    $return .= '  <value>' . $item->getXml() . "</value>\n";
                }
                $return .= '</data></array>';
                return $return;
            case 'struct':
                $return = '<struct>' . "\n";
                foreach ($this->data as $name => $value) {
                    $name = htmlspecialchars($name);
                    $return .= "  <member><name>$name</name><value>";
                    $return .= $value->getXml() . "</value></member>\n";
                }
                $return .= '</struct>';
                return $return;
            case 'date':
            case 'base64':
                return $this->data->getXml();
            default:
                return false;
        }
    }

    /**
     * Checks whether the supplied array is a struct or not
     */
    public function isStruct(array $array): bool
    {
        $expected = 0;
        foreach ($array as $key => $value) {
            if ((string)$key != (string)$expected) {
                return true;
            }
            $expected++;
        }
        return false;
    }
}

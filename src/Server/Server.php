<?php

namespace IXR\Server;


use IXR\DataType\Value;
use IXR\Exception\ServerException;
use IXR\Message\Error;
use IXR\Message\Message;

class Server
{
    protected array $callbacks = [];
    protected ?Message $message;
    protected array $capabilities;

    /**
     * @throws ServerException
     */
    public function __construct(array|false $callbacks = false, string|false $data = false, bool $wait = false)
    {
        $this->setCapabilities();
        if (\is_array($callbacks)) {
            $this->callbacks = $callbacks;
        }
        $this->setCallbacks();
        if (!$wait) {
            $this->serve($data);
        }
    }

    /**
     * @throws ServerException
     */
    public function serve(string|false $data = false): void
    {
        if (!$data) {
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Content-Type: text/plain'); // merged from WP #9093
                throw new ServerException('XML-RPC server accepts POST requests only.');
            }

            $data = file_get_contents('php://input');
        }
        $this->message = new Message($data);
        if (!$this->message->parse()) {
            $this->error(-32700, 'parse error. not well formed');
        }
        if ($this->message->messageType != 'methodCall') {
            $this->error(-32600, 'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall');
        }
        $result = $this->call($this->message->methodName, $this->message->params);

        // Is the result an error?
        if ($result instanceof Error) {
            $this->error($result);
        }

        // Encode the result
        $r = new Value($result);
        $resultxml = $r->getXml();

        // Create the XML
        $xml = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
      $resultxml
      </value>
    </param>
  </params>
</methodResponse>

EOD;
        // Send it
        $this->output($xml);
    }

    /**
     * @param array<mixed>|mixed|null $args
     * @return Error|mixed
     */
    protected function call(string $methodName, mixed $args): mixed
    {
        if (!$this->hasMethod($methodName)) {
            return new Error(-32601, \sprintf('server error. requested method %s does not exist.', $methodName));
        }
        $method = $this->callbacks[$methodName];
        // Perform the callback and send the response

        if (\is_array($args) && \count($args) === 1) {
            // If only one parameter just send that instead of the whole array
            $args = $args[0];
        }

        try {
            // Are we dealing with a function or a method?
            if (\is_string($method) && str_starts_with($method, 'this:')) {
                // It's a class method - check it exists
                $method = substr($method, 5);

                return $this->$method($args);
            }

            return call_user_func($method, $args);
        } catch (\BadFunctionCallException $exception) {
            return new Error(-32601, \sprintf("server error. requested callable '%s' does not exist.", $method));
        }

    }

    public function error(int|Error $error, ?string $message = null): void
    {
        // Accepts either an error object or an error code and message
        if (null !== $message && !($error instanceof Error)) {
            $error = new Error($error, $message);
        }
        $this->output($error->getXml());
    }

    public function output(string $xml): void
    {
        $xml = '<?xml version="1.0"?>' . "\n" . $xml;
        $length = strlen($xml);
        header('Connection: close');
        header('Content-Length: ' . $length);
        header('Content-Type: text/xml');
        header('Date: ' . date('r'));
        echo $xml;
        exit;
    }

    protected function hasMethod(string $method): bool
    {
        return \in_array($method, array_keys($this->callbacks));
    }

    protected function setCapabilities(): void
    {
        // Initialises capabilities array
        $this->capabilities = [
            'xmlrpc' => [
                'specUrl' => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1
            ],
            'faults_interop' => [
                'specUrl' => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516
            ],
            'system.multicall' => [
                'specUrl' => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1
            ],
        ];
    }

    public function getCapabilities(array $args): array
    {
        return $this->capabilities;
    }

    public function setCallbacks(): void
    {
        $this->callbacks['system.getCapabilities'] = 'this:getCapabilities';
        $this->callbacks['system.listMethods'] = 'this:listMethods';
        $this->callbacks['system.multicall'] = 'this:multiCall';
    }

    /**
     * @return array<string>
     */
    public function listMethods(array $args): array
    {
        // Returns a list of methods - uses array_reverse to ensure user defined
        // methods are listed before server defined methods
        return array_reverse(array_keys($this->callbacks));
    }

    public function multiCall(array $methodCalls): array
    {
        // See http://www.xmlrpc.com/discuss/msgReader$1208
        $return = [];
        foreach ($methodCalls as $call) {
            $method = $call['methodName'];
            $params = $call['params'];
            if ($method === 'system.multicall') {
                $result = new Error(-32600, 'Recursive calls to system.multicall are forbidden');
            } else {
                $result = $this->call($method, $params);
            }
            if ($result instanceof Error) {
                $return[] = [
                    'faultCode' => $result->code,
                    'faultString' => $result->message
                ];
            } else {
                $return[] = [$result];
            }
        }
        return $return;
    }
}

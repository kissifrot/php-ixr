<?php
namespace IXR\Client;

use IXR\Message\Error;
use IXR\Message\Message;
use IXR\Request\Request;

/**
 * IXR_Client
 *
 * @package IXR
 * @since   1.5.0
 *
 */
class Client
{
    protected mixed $server;
    protected mixed $port;
    protected mixed $path;
    protected string $useragent;
    protected mixed $response;
    protected bool|Message $message = false;
    protected bool $debug = false;
    /** Connection timeout in seconds */
    protected int|false $timeout;
    /** Timeout for actual data transfer; in seconds */
    protected ?int $timeout_io = null;
    /** @var array<string, mixed> */
    protected array $headers = [];

    /**
     * Storage place for an error message
     */
    private ?Error $error = null;

    public function __construct(string $server, string|false $path = false, int $port = 80, int|false $timeout = 15, ?int $timeout_io = null)
    {
        if (false !== $path) {
            // Assume we have been given a URL instead
            $bits = parse_url($server);
            $this->server = $bits['host'];
            $this->port = $bits['port'] ?? 80;
            $this->path = $bits['path'] ?? '/';

            // Make absolutely sure we have a path
            if (!$this->path) {
                $this->path = '/';
            }

            if (!empty($bits['query'])) {
                $this->path .= '?' . $bits['query'];
            }
        } else {
            $this->server = $server;
            $this->path = $path;
            $this->port = $port;
        }
        $this->useragent = 'The Incutio XML-RPC PHP Library';
        $this->timeout = $timeout;
        $this->timeout_io = $timeout_io;
    }

    public function query(): bool
    {
        $args = func_get_args();
        $method = array_shift($args);
        $request = new Request($method, $args);
        $length = $request->getLength();
        $xml = $request->getXml();
        $lineBreak = "\r\n";
        $request = "POST {$this->path} HTTP/1.0$lineBreak";

        // Merged from WP #8145 - allow custom headers
        $this->headers['Host'] = $this->server;
        $this->headers['Content-Type'] = 'text/xml';
        $this->headers['User-Agent'] = $this->useragent;
        $this->headers['Content-Length'] = $length;

        foreach ($this->headers as $header => $value) {
            $request .= "{$header}: {$value}{$lineBreak}";
        }
        $request .= $lineBreak;

        $request .= $xml;

        // Now send the request
        if ($this->debug) {
            echo '<pre class="ixr_request">' . htmlspecialchars($request) . "\n</pre>\n\n";
        }

        if (false !== $this->timeout) {
            try {
                $fp = fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
            } catch (\Exception $e) {
                $fp = false;
            }
        } else {
            try {
                $fp = fsockopen($this->server, $this->port, $errno, $errstr);
            } catch (\Exception $e) {
                $fp = false;
            }
        }
        if (!$fp) {
            return $this->handleError(-32300, 'transport error - could not open socket');
        }
        if (null !== $this->timeout_io) {
            stream_set_timeout($fp, $this->timeout_io);
        }
        fputs($fp, $request);
        $contents = '';
        $debugContents = '';
        $gotFirstLine = false;
        $gettingHeaders = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if (!$gotFirstLine) {
                // Check line for '200'
                if (!str_contains($line, '200')) {
                    return $this->handleError(-32300, 'transport error - HTTP status code was not 200');
                }
                $gotFirstLine = true;
            }
            if (trim($line) === '') {
                $gettingHeaders = false;
            }
            if (!$gettingHeaders) {
                // merged from WP #12559 - remove trim
                $contents .= $line;
            }
            if ($this->debug) {
                $debugContents .= $line;
            }
        }
        if ($this->debug) {
            echo '<pre class="ixr_response">' . htmlspecialchars($debugContents) . "\n</pre>\n\n";
        }

        // Now parse what we've got back
        $this->message = new Message($contents);
        if (!$this->message->parse()) {
            // XML error
            return $this->handleError(-32700, 'Parse error. Message not well formed');
        }

        // Is the message a fault?
        if ($this->message->messageType === 'fault') {
            return $this->handleError($this->message->faultCode, $this->message->faultString);
        }

        // Message must be OK
        return true;
    }

    public function getResponse()
    {
        // methodResponses can only have one param - return that
        return $this->message->params[0];
    }

    public function isError(): bool
    {
        return (is_object($this->error));
    }

    protected function handleError(int $errorCode, string $errorMessage): false
    {
        $this->error = new Error($errorCode, $errorMessage);

        return false;
    }

    public function getError(): ?Error
    {
        return $this->error;
    }

    public function getErrorCode(): int
    {
        return $this->error->code;
    }

    public function getErrorMessage(): string
    {
        return $this->error->message;
    }


    /**
     * Gets the current timeout set for data transfer
     */
    public function getTimeoutIo(): ?int
    {
        return $this->timeout_io;
    }

    /**
     * Sets the timeout for data transfer
     */
    public function setTimeoutIo(int $timeout_io): void
    {
        $this->timeout_io = $timeout_io;
    }
}

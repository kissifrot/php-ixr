<?php
namespace IXR\Client;

use IXR\Exception\ClientException;
use IXR\Message\Message;
use IXR\Request\Request;

/**
 * Client for communicating with a XML-RPC Server over HTTPS.
 *
 * @author Jason Stirk <jstirk@gmm.com.au> (@link http://blog.griffin.homelinux.org/projects/xmlrpc/)
 * @version 0.2.0 26May2005 08:34 +0800
 * @copyright (c) 2004-2005 Jason Stirk
 * @package IXR
 */
class ClientSSL extends Client
{
    /**
     * Filename of the SSL Client Certificate
     * @since 0.1.0
     */
    private string|false $_certFile;

    /**
     * Filename of the SSL CA Certificate
     * @since 0.1.0
     */
    private string|false $_caFile;

    /**
     * Filename of the SSL Client Private Key
     * @since 0.1.0
     */
    private string|false $_keyFile;

    /**
     * Passphrase to unlock the private key
     * @since 0.1.0
     * @var string
     */
    private string $_passphrase;

    /**
     * Constructor
     * @param string $server URL of the Server to connect to
     * @since 0.1.0
     */
    public function __construct(string $server, string|false $path = false, int $port = 443, int|false $timeout = false, ?int $timeout_io = null)
    {
        parent::__construct($server, $path, $port, $timeout, $timeout_io);
        $this->useragent = 'The Incutio XML-RPC PHP Library for SSL';

        // Set class fields
        $this->_certFile = false;
        $this->_caFile = false;
        $this->_keyFile = false;
        $this->_passphrase = '';
    }

    /**
     * Set the client side certificates to communicate with the server.
     *
     * @param string $certificateFile Filename of the client side certificate to use
     * @param string $keyFile         Filename of the client side certificate's private key
     * @param string $keyPhrase       Passphrase to unlock the private key
     * @throws ClientException
     */
    public function setCertificate(string $certificateFile, string $keyFile, string $keyPhrase = ''): void
    {
        // Check the files all exist
        if (\is_file($certificateFile) && \is_readable($certificateFile)) {
            $this->_certFile = $certificateFile;
        } else {
            throw new ClientException(\sprintf('Could not open certificate: "%s"', $certificateFile));
        }

        if (\is_file($keyFile) && \is_readable($certificateFile)) {
            $this->_keyFile = $keyFile;
        } else {
            throw new ClientException(\sprintf('Could not open private key: "%s"', $keyFile));
        }

        $this->_passphrase = $keyPhrase;
    }

    /**
     * @throws ClientException
     */
    public function setCACertificate(string $caFile): void
    {
        if (\is_file($caFile) && \is_readable($caFile)) {
            $this->_caFile = $caFile;
        } else {
            throw new ClientException(\sprintf('Could not open CA certificate: "%s"', $caFile));
        }
    }

    /**
     * Sets the connection timeout (in seconds)
     * @param int $newTimeOut Timeout in seconds
     * @returns void
     * @since 0.1.2
     */
    public function setTimeOut(int $newTimeOut): void
    {
        $this->timeout = $newTimeOut;
    }

    /**
     * Returns the connection timeout (in seconds)
     * @since 0.1.2
     */
    public function getTimeOut(): int
    {
        return $this->timeout;
    }

    /**
     * Set the query to send to the XML-RPC Server
     * @since 0.1.0
     */
    public function query(): bool
    {
        $args = func_get_args();
        $method = array_shift($args);
        $request = new Request($method, $args);
        $length = $request->getLength();
        $xml = $request->getXml();

        $this->debugOutput('<pre>' . htmlspecialchars($xml) . PHP_EOL . '</pre>');

        //This is where we deviate from the normal query()
        //Rather than open a normal sock, we will actually use the cURL
        //extensions to make the calls, and handle the SSL stuff.

        $curl = curl_init('https://' . $this->server . $this->path);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        //Since 23Jun2004 (0.1.2) - Made timeout a class field
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        if (null !== $this->timeout_io) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout_io);
        }

        if ($this->debug) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
        }

        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        if($this->port !== 443) {
            curl_setopt($curl, CURLOPT_PORT, $this->port);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml",
            "Content-length: {$length}"
        ]);

        // Process the SSL certificates, etc. to use
        if ($this->_certFile !== false) {
            // We have a certificate file set, so add these to the cURL handler
            curl_setopt($curl, CURLOPT_SSLCERT, $this->_certFile);
            curl_setopt($curl, CURLOPT_SSLKEY, $this->_keyFile);

            if ($this->debug) {
                $this->debugOutput(\sprintf('SSL Cert at: "%s"', $this->_certFile));
                $this->debugOutput(\sprintf('SSL Key at: "%s"', $this->_keyFile));
            }

            // See if we need to give a passphrase
            if ($this->_passphrase !== '') {
                curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $this->_passphrase);
            }

            if ($this->_caFile === false) {
                // Don't verify their certificate, as we don't have a CA to verify against
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            } else {
                // Verify against a CA
                curl_setopt($curl, CURLOPT_CAINFO, $this->_caFile);
            }
        }

        // Call cURL to do it's stuff and return us the content
        $contents = curl_exec($curl);
        curl_close($curl);

        // Check for 200 Code in $contents
        if (!str_contains($contents, '200 OK') && !str_contains($contents, 'HTTP/2 200')) {
            //There was no "200 OK" returned - we failed
            return $this->handleError(-32300, 'transport error - HTTP status code was not 200');
        }

        if ($this->debug) {
            $this->debugOutput('<pre>' . htmlspecialchars($contents) . PHP_EOL . '</pre>');
        }
        // Now parse what we've got back
        // Since 20Jun2004 (0.1.1) - We need to remove the headers first
        // Why I have only just found this, I will never know...
        // So, remove everything before the first <
        $contents = substr($contents, strpos($contents, '<'));

        $this->message = new Message($contents);
        if (!$this->message->parse()) {
            // XML error
            return $this->handleError(-32700, 'parse error. not well formed');
        }
        // Is the message a fault?
        if ($this->message->messageType === 'fault') {
            return $this->handleError($this->message->faultCode, $this->message->faultString);
        }

        // Message must be OK
        return true;
    }

    /**
     * Debug output, if debug is enabled
     */
    private function debugOutput(string $message): void
    {
        if ($this->debug) {
            echo $message . PHP_EOL;
        }
    }
}

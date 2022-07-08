<?php

namespace PHRETS\Http;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SimpleXMLElement;

/**
 * Class Response.
 *
 * @method ResponseInterface|StreamInterface getBody
 * @method array getHeaders
 */
class Response
{
    public function __construct(protected ResponseInterface $response)
    {
    }

    /**
     * @throws Exception
     */
    public function xml(): SimpleXMLElement
    {
        $body = (string) $this->response->getBody();

        // Remove any carriage return / newline in XML response.
        $body = trim($body);

        return new SimpleXMLElement($body);
    }

    public function __call($method, array $args = [])
    {
        return call_user_func_array([$this->response, $method], $args);
    }

    public function getHeader(string $name): ?string
    {
        $headers = $this->response->getHeader($name);

        if ($headers) {
            return implode('; ', $headers);
        } else {
            return null;
        }
    }
}

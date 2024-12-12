<?php

namespace Bigcommerce\Api;

use Bigcommerce\Api\Exceptions\ClientException;
use Bigcommerce\Api\Exceptions\ServerException;
use CurlHandle;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP connection.
 */
class NewConnection
{
    /**
     * JSON media type.
     */
    const MEDIA_TYPE_JSON = 'application/json';

    /**
     * Default urlencoded media type.
     */
    const MEDIA_TYPE_WWW = 'application/x-www-form-urlencoded';

    /**
     * @var CurlHandle cURL resource
     */
    public $curl;

    /**
     * @var array<string, string> Hash of HTTP request headers.
     */
    private $headers = [];

    /**
     * @var array<string, string> Hash of headers from HTTP response
     */
    private $responseHeaders = [];

    /**
     * Determines the requested url
     * @var
     */
    private $requestUrl;

    /**
     * Login url for tokenization
     *
     * @var string
     */
    private static $login_url = 'https://login.bigcommerce.com';



    /**
     * @var bool $responseAsArray Whether to return the response body as an array
     */
    private $responseAsArray = false;

    /**
     * @var \GuzzleHttp\Client $client
     */
    private \GuzzleHttp\Client $client;

    /**
     * @var string $responseStatusLine The status line of the response.
     */
    private string $responseStatusLine;

    /**
     * @var int $responseStatus HTTP status code
     */
    private int $responseStatus;

    /**
     * @var string response body
     */
    private string $responseBody;


    /**
     * Initializes the connection object.
     *
     * @param bool $verifyPeer Whether to verify the peer's SSL certificate
     */
    public function __construct(bool $verifyPeer = true)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->headers = [
            'Accept' => $this->getContentType(),
            'Content-Type' => $this->getContentType(),
        ];
    }

    /**
     * Throw an exception if the request encounters an HTTP error condition.
     *
     * <p>An error condition is considered to be:</p>
     *
     * <ul>
     *    <li>400-499 - Client error</li>
     *    <li>500-599 - Server error</li>
     * </ul>
     *
     * <p><em>Note that this doesn't use the builtin CURL_FAILONERROR option,
     * as this fails fast, making the HTTP body and headers inaccessible.</em></p>
     *
     * @param bool $option the new state of this feature
     * @return void
     */
    public function failOnError($option = true)
    {
        $this->failOnError = $option;
    }

    /**
     * Sets the HTTP basic authentication.
     *
     * @param string $username
     * @param string $password
     * @return void
     */
    public function authenticateBasic(string $username, string $password): void
    {
        $this->addHeader('Authorization', 'Basic ' . base64_encode("$username:$password"));
    }

    /**
     * Sets Oauth authentication headers
     *
     * @param string $clientId
     * @param string $authToken
     * @return void
     */
    public function authenticateOauth(string $clientId, string $authToken): void
    {
        $this->addHeader('X-Auth-Client', $clientId);
        $this->addHeader('X-Auth-Token', $authToken);
    }

    /**
     * Add a custom header to the request.
     *
     * @param string $header
     * @param string $value
     * @return void
     */
    public function addHeader(string $header, string $value): void
    {
        $this->headers[$header] = "$value";
    }

    /**
     * Remove a header from the request.
     *
     * @param string $header
     * @return void
     */
    public function removeHeader(string $header): void
    {
        unset($this->headers[$header]);
    }

    /**
     * Return the request headers
     *
     * @return array<string, string>
     */
    public function getRequestHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the MIME type that should be used for this request.
     *
     * Defaults to application/json
     * @return string
     */
    private function getContentType(): string
    {
        return self::MEDIA_TYPE_JSON;
    }

    /**
     * Clear previously cached request data and prepare for
     * making a fresh request.
     * @return void
     */
    private function initializeClient(): void
    {
        $this->responseBody = '';
        $this->responseStatus = 0;
        $this->responseStatusLine = '';
        $this->responseHeaders = [];
        $this->addHeader('Content-Type', $this->getContentType());
        $this->addHeader('Accept', $this->getContentType());
    }

    /**
     * Check the response for possible errors and handle the response body returned.
     *
     * If failOnError is true, a client or server error is raised, otherwise returns false
     * on error.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \stdClass
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private function handleResponse(ResponseInterface $response): void
    {
        $this->responseStatus = $response->getStatusCode();
        $this->responseStatusLine = $response->getReasonPhrase();
        $this->responseHeaders = $response->getHeaders();
        $this->responseBody = $response->getBody()->getContents();

        ray("Response status ".$this->responseStatus);
        if ($this->responseStatus >= 400 && $this->responseStatus <= 499) {
            throw new ClientException($this->responseBody, $this->responseStatus);
        } elseif ($this->responseStatus >= 500 && $this->responseStatus <= 599) {
            throw new ServerException($this->responseBody, $this->responseStatus);
        }
    }

    /**
     * Make an HTTP GET request to the specified endpoint.
     *
     * @param string                     $url URL to retrieve
     * @param array<string, string>|bool $query Optional array of query string parameters
     *
     * @return \stdClass|array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public function get(string $url, array $query = null):\stdClass|array
    {

        if (is_array($query)) $url .= '?' . http_build_query($query);

        $this->initializeClient();
        $response = $this->client->get($url, ['headers' => $this->getRequestHeaders(),]);
        $this->handleResponse($response);

        return $this->getBody();
    }

    /**
     * Make an HTTP POST request to the specified endpoint.
     *
     * @param string $url URL to which we send the request
     * @param mixed  $body Data payload (JSON string or raw data)
     *
     * @return mixed
     * @throws \Bigcommerce\Api\ClientError
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     * @throws \Bigcommerce\Api\NetworkError
     * @throws \Bigcommerce\Api\ServerError
     */
    public function post(string $url, array $body): \stdClass
    {
        $body = json_encode($body);
        $this->initializeClient();

        $this->handleResponse($this->client->post($url, [
            'headers' => $this->getRequestHeaders(),
            'body' => $body,
        ]));

        return $this->getBody();
    }

    /**
     * Make an HTTP HEAD request to the specified endpoint.
     *
     * @param string $url URL to which we send the request
     * @return mixed
     */
    public function head(string $url)
    {
        $this->initializeClient();
        $this->handleResponse($this->head($url, $this->getHeaders()));
    }

    /**
     * Make an HTTP PUT request to the specified endpoint.
     *
     * Requires a tmpfile() handle to be opened on the system, as the cURL
     * API requires it to send data.
     *
     * @param string $url URL to which we send the request
     * @param mixed $body Data payload (JSON string or raw data)
     * @return mixed
     */
    public function put(string $url, array $body):\stdClass|null
    {

        $this->initializeClient();

        $body = json_encode($body);

        $this->handleResponse($this->client->put($url, [
            'headers' => $this->getRequestHeaders(),
            'body' => $body,
        ]));

        return $this->getBody() ?? null;
    }

    /**
     * Make an HTTP DELETE request to the specified endpoint.
     *
     * @param string $url URL to which we send the request
     * @return void
     */
    public function delete(string $url): void
    {
        $this->initializeClient();
        $this->handleResponse($this->client->delete($url, ['headers' => $this->getRequestHeaders()]));
    }

    /**
     * Access the content body of the response
     *
     * @return string
     */
    public function getBody():mixed
    {
        return json_decode($this->responseBody);
    }

    /**
     * Access given header from the response.
     *
     * @param string $header Header name to retrieve
     *
     * @return string|void
     */
    public function getHeader(string $header)
    {
        return $this->responseHeaders[$header] ?? null;
    }

    /**
     * Return the full list of response headers
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->responseHeaders;
    }
}

<?php

namespace Bigcommerce\Api;

use Bigcommerce\Api\Exceptions\ClientException;
use Bigcommerce\Api\Resources\Address;
use Bigcommerce\Api\Resources\Brand;
use Bigcommerce\Api\Resources\Category;
use Bigcommerce\Api\Resources\CategoryTree;
use Bigcommerce\Api\Resources\Customer;
use Bigcommerce\Api\Resources\CustomerAttribute;
use Bigcommerce\Api\Resources\CustomerGroup;
use Bigcommerce\Api\Resources\Location;
use Bigcommerce\Api\Resources\Option;
use Bigcommerce\Api\Resources\OptionValue;
use Bigcommerce\Api\Resources\Order;
use Bigcommerce\Api\Resources\OrderCoupons;
use Bigcommerce\Api\Resources\OrderProduct;
use Bigcommerce\Api\Resources\Pricelist;
use Bigcommerce\Api\Resources\ProductCustomField;
use Bigcommerce\Api\Resources\ProductImage;
use Bigcommerce\Api\Resources\ProductOption;
use Bigcommerce\Api\Resources\ResourceEnum;
use Bigcommerce\Api\Resources\ProductVariant;
use Bigcommerce\Api\Resources\Product;
use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use phpDocumentor\Reflection\Types\Self_;
use Sabre\DAV\Collection;
use function Symfony\Component\String\s;

/**
 * Bigcommerce API Client.
 */
class NewClient
{
    /**
     * @var string $OAUTH_MODE The OAuth connection mode
     */
    const OAUTH_MODE = 'OAUTH';

    /**
     * @var string $BASIC_AUTH_MODE The basic auth connection mode
     */
    const BASIC_AUTH_MODE = 'BASIC_AUTH';

    /**
     * @var string $connectionMode The connection mode to use
     * @static
     */
    private static string $connectionMode = self::OAUTH_MODE;

    /**
     * @var string $storeUrl URL to the store API
     * @static
     */
    private static string $storeUrl;

    /**
     * @var string $username Username for basic auth
     * @static
     */
    private static string $username;

    /**
     * @var string $apiKey API key for basic auth
     */
    private static string $apiKey;

    /**
     * @var \Bigcommerce\Api\NewConnection|null $connection The HTTP connection object
     * @static
     */
    private static ?NewConnection $connection = null;

    /**
     * Resource class name
     *
     * @var string
     */
    private static string $resource;

    /**
     * @var string $pathPrefix URL pathname prefix for the V3 API
     * @static
     */
    private static string $pathPrefix = 'api/v3';

    /**
     * @var string $apiPath URL to the store API
     * @static
     */
    public static $apiPath;

    /**
     * @var string $legacyApiPath URL to the store API (v2)
     * @static
     */
    public static $legacyApiPath;

    /**
     * @var string $clientId The OAuth client ID
     * @static
     */
    private static $clientId;

    /**
     * @var string $storeHash The store hash
     * @static
     */
    private static $storeHash;

    /**
     * @var string $authToken The OAuth Auth-Token
     * @static
     */
    private static $authToken;

    /**
     * @var ?string $clientSecret The OAuth client secret
     * @static
     */
    private static ?string $clientSecret;

    /**
     * @var bool The direcrive to verify peer
     * @static
     */
    private static bool $verifyPeer = true;

    /**
     * @var string $storesPrefix URL pathname prefix for the V2 API
     * @static
     */
    private static $storesPrefix = '/stores/%s/v3';

    /**
     * @var string $legacyStoresPrefix URL pathname prefix for the V2 API
     * @static
     */
    private static $legacyStoresPrefix = '/stores/%s/v2';

    /**
     * @var string $apiUrl The BigCommerce store management API host
     * @static
     */
    private static $apiUrl = 'https://api.bigcommerce.com';

    /**
     * @var string $loginUrl The BigCommerce merchant login URL
     * @static
     */
    private static $loginUrl = 'https://login.bigcommerce.com';


    /**************************************************************************
     * Handling configuration and connection
     **************************************************************************/

    /**
     * Configure the API client with the required Oauth/BasicAuth credentials.
     *
     * @param array $settings
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @static
     */
    static public function configure(array $settings): void
    {
        self::configureBaseSettings($settings);
        self::$connectionMode === self::OAUTH_MODE ? self::configureOAuth($settings) : self::configureBasicAuth($settings);
    }

    /**
     * Swaps a temporary access code for a long expiry auth token.
     *
     * @param \stdClass|array $object
     *
     * @return \stdClass
     */
    public static function getAuthToken($object)
    {
        $context = array_merge(array('grant_type' => 'authorization_code'), (array)$object);
        $connection = new NewConnection();

        return $connection->post(self::$loginUrl . '/oauth2/token', $context);
    }

    /**
     * Configure the API client with the required OAuth credentials.
     *
     * Requires a settings array to be passed in with the following keys:
     *
     * - client_id
     * - auth_token
     * - store_hash
     *
     * @param array<string, string> $settings
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     */
    public static function configureOAuth(array $settings): void
    {
        if (!isset($settings['auth_token'])) throw new ClientException('NewClient configureOauth: auth_token must be provided');
        if (!isset($settings['store_hash'])) throw new ClientException('NewClient configureOauth: store_hash must be provided');
        if (!isset($settings['client_id'])) throw new ClientException('NewClient configureOauth: client_id must be provided');

        self::$clientId = $settings['client_id'];
        self::$authToken = $settings['auth_token'];
        self::$storeHash = $settings['store_hash'];

        self::$clientSecret = $settings['client_secret'] ?? null;
        self::$apiPath = self::$apiUrl . sprintf(self::$storesPrefix, self::$storeHash);
        self::$legacyApiPath = self::$apiUrl . sprintf(self::$legacyStoresPrefix, self::$storeHash);

        self::$connection = null;
    }

    /**
     * Configure the API client with the required basic auth credentials.
     * Requires a settings array to be passed in with the following
     * keys:
     * - store_url
     * - username
     * - api_key
     *
     * @param array $settings
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     */
    public static function configureBasicAuth(array $settings): void
    {
        if (!isset($settings['store_url'])) throw new ClientException('NewClient configureBasicAuth: store_url must be provided');
        if (!isset($settings['username'])) throw new ClientException('NewClient configureBasicAuth: username must be provided');
        if (!isset($settings['api_key'])) throw new ClientException('NewClient configureBasicAuth: api_key must be provided');

        self::$username = $settings['username'];
        self::$apiKey = $settings['api_key'];
        self::$storeUrl = rtrim($settings['store_url'], '/');
        self::$apiPath = self::$storeUrl . self::$pathPrefix;

        self::$connection = null;
    }

    /**
     * Configure base settings for the API client.
     * Requires a settings array to be passed in with the following
     * keys:
     * - connection_mode
     *
     * @param array $settings
     *
     * @return void
     */
    private static function configureBaseSettings(array $settings): void
    {
        self::$verifyPeer = $settings['verify_peer'] ?? true;
        self::$connectionMode = $settings['connection_mode'] ?? self::OAUTH_MODE;
    }

    /**
     * Get an instance of the HTTP connection object. Initializes
     * the connection if it is not already active.
     *
     * @return NewConnection
     */
    private static function connection(): NewConnection
    {
        // If the connection is not already initialized, create, authorize and configure it.
        if (!self::$connection) {
            self::$connection = new NewConnection(true);
            self::$connectionMode === self::OAUTH_MODE ?
                self::$connection->authenticateOauth(self::$clientId, self::$authToken)
                :
                self::$connection->authenticateBasic(self::$username, self::$apiKey);
        }

        return self::$connection;
    }


    /**************************************************************************
     * Handling resource CRUD
     **************************************************************************/

    /**
     * Get a collection result from the specified endpoint.
     *
     * @param string $pathWithLeadingSlash
     * @param \Bigcommerce\Api\Resources\ResourceEnum $resource resource class to map individual items
     * @param bool $legacy
     *
     * @return array Mapped collection
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private static function getCollection(string $pathWithLeadingSlash, string $resource = 'Resource', bool $legacy = false): array
    {
        $composedPath = $legacy ? self::$legacyApiPath : self::$apiPath;

        $response = self::connection()->get(url: $composedPath . $pathWithLeadingSlash);

        if ($legacy) {
            return self::mapCollection($resource, $response);
        }

        $data = $response->data;
        $pagination = $response->meta->pagination;
        if (isset($pagination->links->next)) {
            $needsNext = true;
            while ($needsNext) {
                $next = $pagination->links->next;
                $response = self::connection()->get(self::$apiPath . $pathWithLeadingSlash . $next);
                $data = array_merge($data, $response->data);
                $pagination = $response->meta->pagination;
                if (!isset($pagination->links->next)) $needsNext = false;
            }
        }

        return self::mapCollection($resource, $data, $legacy);
    }

    /**
     * Get a resource entity from the specified endpoint.
     *
     * @param string $path api endpoint
     * @param string $resource resource class to map individual items
     *
     * @return mixed Resource|string resource object or XML string if useXml is true
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private static function getResource(string $path, string $resource = 'Resource', $legacy = false): mixed
    {
        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $response = self::connection()->get($composedPath);
        return self::mapResource($resource, $response);
    }

    /**
     * Get a count value from the specified endpoint.
     *
     * @param string $path api endpoint
     *
     * @return string|int|bool int
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCount(string $path, $legacy = false): int
    {
        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $response = self::connection()->get($composedPath);
        return self::mapCount($response);
    }

    /**
     * Send a post request to create a resource on the specified collection.
     *
     * @param string $path api endpoint
     * @param array $object object to create
     *
     * @return Resource|null
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private static function createResource(string $path, array $object, string $resource = 'Resource', bool $legacy = false): Resource
    {
        ray('CreateResource', $path, $object, $resource);

        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $post = self::connection()->post($composedPath, $object);
        return self::mapResource($resource, $post);
    }

    /**
     * Send a put request to update the specified resource.
     *
     * @param string $path api endpoint
     * @param array $object object or XML string to update
     * @param bool $legacy
     *
     * @return \Bigcommerce\Api\Resource|null
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private static function updateResource(string $path, array $object, string $resource, bool $legacy = false): Resource|null
    {
        ray('UpdateResource', $path, json_encode($object), $resource);

        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $put = self::connection()->put($composedPath, $object);
        return self::mapResource($resource, $put);
    }

    /**
     * Perform a not-related resource update.
     * @param string $path
     * @param array $object
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    private static function updateRaw(string $path, array $object): void
    {
        ray('UpdateRaw', $path, json_encode($object));

        $composedPath = self::$apiPath . $path;
        $put = self::connection()->put($composedPath, $object);
    }

    /**
     * Send a delete request to remove the specified resource.
     *
     * @param string $path api endpoint
     *
     * @return void
     */
    private static function deleteResource(string $path): void
    {
        self::connection()->delete((self::$apiPath) . $path);
    }

    /**************************************************************************
     * Handling resource mapping
     **************************************************************************/

    /**
     * Internal method to wrap items in a collection to resource classes.
     *
     * @param string $resource name of the resource class
     * @param mixed $object object collection
     * @param bool $legacy
     *
     * @return array|null
     */
    private static function mapCollection(string $resource, array $object, bool $legacy = false): array|null
    {
        if (!is_array($object)) return null;

        $baseResource = __NAMESPACE__ . '\\' . $resource;
        self::$resource = (class_exists($baseResource)) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;
        return array_map(array(self::class, 'mapCollectionObject'), $object);
    }

    /**
     * Callback for mapping collection objects resource classes.
     *
     * @param \stdClass $object
     *
     * @return Resource
     */
    private static function mapCollectionObject($object): Resource
    {
        $class = self::$resource;
        return new $class($object);
    }

    /**
     * Map a single object to a resource class.
     *
     * @param string $resource name of the resource class
     * @param \stdClass $object
     *
     * @return Resource
     */
    private static function mapResource(string $resource, \stdClass $object): Resource|null
    {

        if (isset($object->data)) $object = $object->data;
        $baseResource = __NAMESPACE__ . '\\' . $resource;
        $class = (class_exists($baseResource)) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;
        return new $class($object);
    }

    /**
     * Map object representing a count to an integer value.
     *
     * @param \stdClass $object
     *
     * @return int|false
     */
    private static function mapCount(?array $object): int
    {
        if (!is_array($object)) return 0;
        return $object['count'];
    }


    /**************************************************************************
     * Handling API Functions
     **************************************************************************/

    /**
     * Pings the time endpoint to test the connection to a store.
     *
     * @return ?DateTime
     */
    public static function getTime(): DateTime|null
    {
        $response = self::connection()->get(self::$apiUrl . '/time');
        if (empty($response)) return null;
        return new DateTime("@{$response}");
    }

    /**************************************************************************
     * PRICELISTS
     **************************************************************************/

    /**
     * Create a pricelist
     * @param array $object
     *
     * @return Pricelist
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    static public function createPricelist(array $object): Pricelist
    {
        return self::createResource('/pricelists', $object, 'Pricelist');
    }

    /**
     * Update a pricelist
     *
     * @param int $pricelistId
     * @param array $object
     *
     * @return Pricelist
     */
    static public function updatePricelist(int $pricelistId, array $object): Pricelist
    {
        return self::updateResource('/pricelists/' . $pricelistId, $object, 'Pricelist');
    }

    /**
     * Returns the default collection of pricelists
     *
     * @param array $filter
     *
     * @return mixed array|string list of pricelists
     */
    public static function getPricelists(array $filter = array()): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/pricelists' . $filter->toQuery(), 'Pricelist');
    }

    /**
     * Upsert a pricelist to a customer group in a channel
     *
     * @param $pricelistId
     * @param $customerGroupId
     * @param $channelId
     *
     * @return mixed|\stdClass|null
     */
    public static function upsertPricelistToCustomerGroup(
        int $pricelistId, int $customerGroupId, int $channelId): void
    {
        $object = [
            'customer_group_id' => $customerGroupId,
            'channel_id' => $channelId,
        ];

        $subpath = '/pricelists/' . $pricelistId . '/assignments';

        self::updateRaw(self::$apiPath . $subpath, $object);

    }

    /**
     * Upsert pricelist records
     *
     * @param int $pricelistId
     * @param array $records
     *
     * @return void
     */
    public static function upsertPricelistRecords(int $pricelistId, array $records): void
    {
        $subpath = '/pricelists/' . $pricelistId . '/records';
        self::connection()->put(self::$apiPath . $subpath, $records);
    }

    /**
     * Delete a pricelist
     *
     * @param int $pricelistId
     * @return null
     *
     */
    static public function deletePricelist(int $pricelistId): void
    {
        self::deleteResource('/pricelists/' . $pricelistId);
    }

    /**
     * @param int $pricelistId
     *
     * @return array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    static public function getAllPricelistRecords(int $pricelistId): array
    {
        return self::getCollection('/pricelists/' . $pricelistId . '/records', 'PricelistRecord');
    }


    /**************************************************************************
     * PRODUCTS
     **************************************************************************/

    /**
     * Returns the default collection of products.
     *
     * @param array $filter
     *
     * @return array list of products
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getProducts(array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/catalog/products' . $filter->toQuery(), 'Product');
    }

    /**
     * Returns a single product resource by the given id.
     *
     * @param int $id product id
     *
     * @return Resources\Product
     */
    public static function getProduct($id): Product
    {
        return self::getResource('/catalog/products/' . $id, 'Product');
    }

    /**
     * Create a new product.
     *
     * @param array $productFields Product fields
     *
     * @return \Bigcommerce\Api\Resources\Product
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function createProduct(array $productFields): Product
    {
        return self::createResource('/catalog/products', $productFields, 'Product');
    }

    /**
     * Update the given product.
     *
     * @param int $id product id
     * @param array $object fields to update
     *
     * @return Product
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function updateProduct(int $id, array $productFields): Product
    {
        return self::updateResource('/catalog/products/' . $id, $productFields, 'Product');
    }

    /**
     * Delete the given product.
     *
     * @param int $id product id
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function deleteProduct(int $id): void
    {
        self::deleteResource('/catalog/products/' . $id);
    }

    /**
     * Delete all products.
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function deleteAllProducts(): void

    {
        self::deleteResource('/catalog/products');
    }

    /**************************************************************************
     * CUSTOMERS
     **************************************************************************/
    /**
     * The list of customers.
     *
     * @param array $filter
     * @return array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomers($filter = array()): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/customers' . $filter->toQuery(), 'Customer');
    }

    /**
     * The total number of customers in the collection.
     *
     * @param array $filter
     *
     * @return int
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomersCount($filter = array()): int
    {
        $filter = Filter::create($filter);
        return self::getCount('/customers/count' . $filter->toQuery());
    }

    /**
     * A single customer by given id.
     *
     * @param int $id customer id
     *
     * @return Resources\Customer
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomer(int $id): Customer
    {
        return self::getResource('/customers?id:in=' . $id, 'Customer');
    }

    /**
     * Get customer attributes
     * @param array $filter
     *
     * @return array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomerAttributes(array $filter = array()): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/customers/attributes' . $filter->toQuery(), 'CustomerAttribute');
    }

    /**
     * Create a new client
     *
     * @param array $object
     *
     * @return mixed|\stdClass
     * @throws \Bigcommerce\Api\ClientError
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     * @throws \Bigcommerce\Api\NetworkError
     * @throws \Bigcommerce\Api\ServerError
     */
    public static function createCustomer(array $object): Customer
    {
        return self::createResource('/customers', $object);
    }

    /**
     * Create a new customer attribute
     *
     * @param array $object
     *
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function createCustomerAttribute(array $object): CustomerAttribute
    {
        return self::createResource('/customers/attributes', $object);
    }

    /**
     * Update the given customer.
     *
     * @param int $id
     * @param array $object
     *
     * @return \Bigcommerce\Api\Resources\Customer
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function updateCustomer(int $id, array $object): Customer
    {
        return self::updateResource('/customers', [array_merge(['id' => $id], $object)], 'Customer');
    }

    /**
     * Delete the given customer.
     *
     * @param int $id customer id
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function deleteCustomer(int $id): void
    {
        self::deleteResource('/customers?id:in=' . $id);
    }

    /**
     * Delete all customers
     *
     * @return mixed
     */
    public static function deleteCustomers(array $ids): void
    {
        self::deleteResource('/customers?id:in=' . implode(',', $ids));
    }


    /**
     *  A list of addresses belonging to the given customer.
     *
     * @param int $id
     *
     * @return array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomerAddresses(int $id): array
    {
        return self::getCollection(
            '/customers/' . $id . '/addresses', 'Address');
    }


    /**************************************************************************
     * CATEGORIES
     **************************************************************************/

    /**
     * The collection of categories.
     *
     * @param array $filter
     *
     * @return array
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCategories(array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/catalog/categories' . $filter->toQuery(), 'Category');
    }

    /**
     * The number of categories in the collection.
     *
     * @param array $filter
     *
     * @return int
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCategoriesCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/catalog/categories/count' . $filter->toQuery());
    }

    /**
     * A single category by given id.
     *
     * @param int $id category id
     *
     * @return Resources\Category
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCategory(int $id): Category
    {
        return self::getResource('/catalog/categories/' . $id, 'Category');
    }

    /**
     * Create a new category from the given data.
     *
     * @param array $object Category data
     *
     * @return Category
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function createCategory(array $object): Category
    {
        return self::createResource('/catalog/categories', $object, 'Category');
    }

    /**
     * Update the given category.
     *
     * @param int $id Category id
     * @param array $object Category data
     *
     * @return Category
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function updateCategory(int $id, array $object): Category
    {
        return self::updateResource('/catalog/categories/' . $id, $object, 'Category');
    }

    /**
     * Delete the given category.
     *
     * @param int $id Category id
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function deleteCategory(int $id): void
    {
        self::deleteResource('/catalog/categories/' . $id);
    }

    /**
     * Delete all categories.
     *
     * @return void
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function deleteAllCategories(): void
    {
        self::deleteResource('/catalog/categories');
    }

    /**
     * Returns the list of locations.
     * @param $filter
     *
     * @return array
     */
    public static function getLocations($filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/inventory/locations', 'Location');
    }

    /**
     * Return a single location
     * @param int $id
     *
     * @return \Bigcommerce\Api\Location
     */
    public static function getLocation(int $id): Location
    {
        return self::getResource('/inventory/locations/' . $id, 'Location');
    }

    /**
     * Create a new location.
     *
     * @param array $object fields to create
     *
     * @return Location
     */
    public static function createLocation(array $object): Location
    {
        return self::createResource('/inventory/locations', $object, 'Location');
    }

    /**
     * Update the given location.
     *
     * @param int $id product id
     * @param array $object fields to update
     *
     * @return Location
     */
    public static function updateLocation(int $id, array $object): Location
    {
        return self::updateResource('/inventory/locations/' . $id, $object, 'Location');
    }

    /**
     * Gets collection of images for a product.
     *
     * @param int $id product id
     *
     * @return mixed array
     */
    public static function getProductImages(int $id): array
    {
        return self::getCollection('/catalog/products/' . $id . '/images', 'ProductImage');
    }

    /**
     * Gets collection of custom fields for a product.
     *
     * @param int $id product ID
     *
     * @return array
     */
    public static function getProductCustomFields(int $id): array
    {
        return self::getCollection('/catalog/products/' . $id . '/custom-fields', 'ProductCustomField');
    }

    /**
     * Returns a single custom field by given id
     * @param int $productId
     * @param int $customFieldId
     *
     * @return \Bigcommerce\Api\Resources\ProductCustomField
     */
    public static function getProductCustomField(int $productId, int $customFieldId): ProductCustomField
    {
        return self::getResource('/catalog/products/' . $productId . '/custom-fields/' . $customFieldId, 'ProductCustomField');
    }

    /**
     * Create a new custom field for a given product.
     *
     * @param int $product_id product id
     * @param mixed $object fields to create
     *
     * @return Object Object with `id`, `product_id`, `name` and `text` keys
     */
    public static function createProductCustomField(int $productId, array $object): ProductCustomField
    {
        return self::createResource('/catalog/products/' . $productId . '/custom-fields', $object, 'ProductCustomField');
    }

    /**
     * Gets collection of reviews for a product.
     *
     * @param $id
     *
     * @return mixed
     */
    public static function getProductReviews($id)
    {
        return self::getCollection('/products/' . $id . '/reviews/', 'ProductReview');
    }

    /**
     * Update the given custom field.
     *
     * @param int $product_id product id
     * @param int $id custom field id
     * @param mixed $object custom field to update
     *
     * @return mixed
     */
    public static function updateProductCustomField(int $productId, int $customFieldId, arrray $object): ProductCustomField
    {
        return self::updateResource('/catalog/products/' . $productId . '/custom-fields/' . $customFieldId, $object);
    }

    /**
     * Delete the given custom field.
     *
     * @param int $product_id product id
     * @param int $id custom field id
     *
     * @return void
     */
    public static function deleteProductCustomField(int $productId, int $customFieldId): void
    {
        self::deleteResource('/catalog/products/' . $productId . '/custom-fields/' . $customFieldId);
    }

    /**
     * Returns the total number of products in the collection.
     *
     * @param array $filter
     *
     * @return int|string number of products or XML string if useXml is true
     */
    public static function getProductsCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/products/count' . $filter->toQuery());
    }


    public static function bulkUpdateProducts($object)
    {
        $subpath = '/catalog/products';
        return self::$connection->put(self::$apiPath_v3 . $subpath, $object);
    }

    /**
     * Assign the product to the given channel
     *
     * @param $productId
     * @param $channelId
     * @param $v
     *
     * @return mixed
     */
    public static function assignProductToChannelId(int $productId, int $channelId): void
    {
        $object = [
            'product_id' => $productId,
            'channel_id' => $channelId,
        ];

        self::connection()->put(self::$apiPath . '/catalog/products/channel-assignments', [$object]);
    }

    public static function assignLayoutToProductInChannelId(int $productId, int $channelId, string $layoutFile): void
    {
        $object = [
            'entity_type' => 'product',
            'entity_id' => $productId,
            'channel_id' => $channelId,
            'file_name' => $layoutFile,
        ];

        self::connection()->put(self::$apiPath . '/storefront/custom-template-associations', [$object]);
    }

    /**
     * Return the collection of options.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getOptions($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/options' . $filter->toQuery(), 'Option');
    }

    /**
     * Create Options
     *
     * @param $object
     *
     * @return mixed
     */
    public static function createOption($object)
    {
        return self::createResource('/options', $object);
    }

    /**
     * Update the given option.
     *
     * @param int $id category id
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateOption($id, $object)
    {
        return self::updateResource('/options/' . $id, $object);
    }

    /**
     * Return the number of options in the collection
     *
     * @return int
     */
    public static function getOptionsCount()
    {
        return self::getCount('/options/count');
    }

    /**
     * Return a single option by given id.
     *
     * @param int $id option id
     *
     * @return Resources\Option
     */
    public static function getOption($id)
    {
        return self::getResource('/options/' . $id, 'Option');
    }

    /**
     * Delete the given option.
     *
     * @param int $id option id
     *
     * @return mixed
     */
    public static function deleteOption($id)
    {
        return self::deleteResource('/options/' . $id);
    }

    /**
     * Return a single value for an option.
     *
     * @param int $option_id option id
     * @param int $id value id
     *
     * @return Resources\OptionValue
     */
    public static function getOptionValue($option_id, $id)
    {
        return self::getResource('/options/' . $option_id . '/values/' . $id, 'OptionValue');
    }

    /**
     * Return the collection of all option values.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getOptionValues($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/options/values' . $filter->toQuery(), 'OptionValue');
    }

    /**
     * The collection of brands.
     *
     * @param array $filter
     *
     * @return Brand[]
     */
    public static function getBrands(array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/catalog/brands' . $filter->toQuery(), 'Brand');
    }

    /**
     * The total number of brands in the collection.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function getBrandsCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/brands/count' . $filter->toQuery());
    }

    /**
     * A single brand by given id.
     *
     * @param int $id brand id
     *
     * @return Brand
     */
    public static function getBrand(int $id): Brand
    {
        return self::getResource('/catalog/brands/' . $id, 'Brand');
    }

    /**
     * Create a new brand from the given data.
     *
     * @param mixed $object
     *
     * @return Brand
     */
    public static function createBrand($object): Brand
    {
        return self::createResource('/catalog/brands', $object, 'Brand');
    }

    /**
     * Update the given brand.
     *
     * @param int $id brand id
     * @param mixed $object
     *
     * @return Brand
     */
    public static function updateBrand(int $id, $object): Brand
    {
        return self::updateResource('/catalog/brands/' . $id, $object, 'Brand');
    }

    /**
     * Delete the given brand.
     *
     * @param int $id brand id
     *
     * @return void
     */
    public static function deleteBrand(int $id): void
    {
        self::deleteResource('/catalog/brands/' . $id);
    }

    /**
     * Delete all brands.
     *
     * @return void
     */
    public static function deleteAllBrands(): void
    {
        self::deleteResource('/catalog/brands');
    }

    /**
     * The collection of orders.
     *
     * @param array $filter
     *
     * @return array<Order>
     */
    public static function getOrders(array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders' . $filter->toQuery(), 'Order', legacy: true);
    }

    /**
     * The number of orders in the collection.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function getOrdersCount(array $filter = [])
    {
        $filter = Filter::create($filter);
        return self::getCount('/orders/count' . $filter->toQuery(), legacy: true);
    }

    /**
     * The order count grouped by order status
     *
     * @param array $filter
     *
     * @return Resources\OrderStatus
     */
    public static function getOrderStatusesWithCounts(array $filter = [])
    {
        $filter = Filter::create($filter);
        $resource = self::getResource('/orders/count' . $filter->toQuery(), "OrderStatus", legacy: true);
        return $resource->statuses;
    }

    /**
     * A single order.
     *
     * @param int $id order id
     *
     * @return Resources\Order
     */
    public static function getOrder(int $id): Order
    {
        return self::getResource('/orders/' . $id, 'Order', legacy: true);
    }

    /**
     * @param $orderID
     *
     * @return array<OrderProduct>
     */
    public static function getOrderProducts(int $orderID): array
    {
        return self::getCollection('/orders/' . $orderID . '/products', 'OrderProduct', legacy: true);
    }

    /**
     * The total number of order products in the collection.
     *
     * @param       $orderID
     * @param array $filter
     *
     * @return mixed
     */
    public static function getOrderProductsCount(int $orderID, array $filter = [])
    {
        $filter = Filter::create($filter);
        return self::getCount('/orders/' . $orderID . '/products/count' . $filter->toQuery(), legacy: true);
    }

    /**
     * Delete the given order (unlike in the Control Panel, this will permanently
     * delete the order).
     *
     * @param int $id order id
     *
     * @return void
     */
    public static function deleteOrder(int $id): void
    {
        self::deleteResource('/orders/' . $id);
    }

    /**
     * Delete all orders.
     *
     * @return void
     */
    public static function deleteAllOrders(): void
    {
        self::deleteResource('/orders');
    }

    /**
     * Create an order
     *
     * @param $object
     *
     * @return mixed
     */
    public static function createOrder($object)
    {
        return self::createResource('/orders', $object);
    }

    /**
     * Update the given order.
     *
     * @param int $id order id
     * @param mixed $object fields to update
     *
     * @return mixed
     */
    public static function updateOrder($id, $object)
    {
        return self::updateResource('/orders/' . $id, $object);
    }


    /**
     * Returns the collection of option sets.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getOptionSets($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/optionsets' . $filter->toQuery(), 'OptionSet');
    }

    /**
     * Create Optionsets
     *
     * @param $object
     *
     * @return mixed
     */
    public static function createOptionSet($object)
    {
        return self::createResource('/optionsets', $object);
    }

    /**
     * Create Option Set Options
     *
     * @param $object
     * @param $id
     *
     * @return mixed
     */
    public static function createOptionSetOption($object, $id)
    {
        return self::createResource('/optionsets/' . $id . '/options', $object);
    }

    /**
     * Returns the total number of option sets in the collection.
     *
     * @return int
     */
    public static function getOptionSetsCount()
    {
        return self::getCount('/optionsets/count');
    }

    /**
     * A single option set by given id.
     *
     * @param int $id option set id
     *
     * @return Resources\OptionSet
     */
    public static function getOptionSet($id)
    {
        return self::getResource('/optionsets/' . $id, 'OptionSet');
    }

    /**
     * Update the given option set.
     *
     * @param int $id option set id
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateOptionSet($id, $object)
    {
        return self::updateResource('/optionsets/' . $id, $object);
    }

    /**
     * Delete the given option set.
     *
     * @param int $id option id
     *
     * @return mixed
     */
    public static function deleteOptionSet($id)
    {
        NewClient::deleteResource('/optionsets/' . $id);
    }

    /**
     * Status code used to represent the state of an order.
     *
     * @param int $id order status id
     *
     * @return mixed
     */
    public static function getOrderStatus($id)
    {
        return self::getResource('/order_statuses/' . $id, 'OrderStatus');
    }

    /**
     * Status codes used to represent the state of an order.
     *
     * @return array
     */
    public static function getOrderStatuses()
    {
        return self::getCollection('/order_statuses', 'OrderStatus');
    }

    /**
     * Get collection of product skus
     *
     * @param array $filter
     *
     * @return mixed
     */
    public static function getSkus($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products/skus' . $filter->toQuery(), 'Sku');
    }

    /**
     * Create a SKU
     *
     * @param $productId
     * @param $object
     *
     * @return mixed
     */
    public static function createSku($productId, $object)
    {
        return self::createResource('/products/' . $productId . '/skus', $object);
    }

    /**
     * Update sku
     *
     * @param $id
     * @param $object
     *
     * @return mixed
     */
    public static function updateSku($id, $object)
    {
        return self::updateResource('/products/skus/' . $id, $object);
    }

    /**
     * Returns the total number of SKUs in the collection.
     *
     * @return int
     */
    public static function getSkusCount()
    {
        return self::getCount('/products/skus/count');
    }

    /**
     * Returns the googleproductsearch mapping for a product.
     *
     * @return Resources\ProductGoogleProductSearch
     */
    public static function getGoogleProductSearch($productId)
    {
        return self::getResource('/products/' . $productId . '/googleproductsearch', 'ProductGoogleProductSearch');
    }

    /**
     * Get a single coupon by given id.
     *
     * @param int $id customer id
     *
     * @return Resources\Coupon
     */
    public static function getCoupon($id)
    {
        return self::getResource('/coupons/' . $id, 'Coupon');
    }

    /**
     * Get coupons
     *
     * @param array $filter
     *
     * @return mixed
     */
    public static function getCoupons($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/coupons' . $filter->toQuery(), 'Coupon');
    }

    /**
     * Create coupon
     *
     * @param $object
     *
     * @return mixed
     */
    public static function createCoupon($object)
    {
        return self::createResource('/coupons', $object);
    }

    /**
     * Update coupon
     *
     * @param $id
     * @param $object
     *
     * @return mixed
     */
    public static function updateCoupon($id, $object)
    {
        return self::updateResource('/coupons/' . $id, $object);
    }

    /**
     * Delete the given coupon.
     *
     * @param int $id coupon id
     *
     * @return mixed
     */
    public static function deleteCoupon($id)
    {
        return self::deleteResource('/coupons/' . $id);
    }

    /**
     * Delete all Coupons.
     *
     * @return mixed
     */
    public static function deleteAllCoupons()
    {
        return self::deleteResource('/coupons');
    }

    /**
     * Return the number of coupons
     *
     * @return int
     */
    public static function getCouponsCount()
    {
        return self::getCount('/coupons/count');
    }

    /**
     * The request logs with usage history statistics.
     */
    public static function getRequestLogs()
    {
        return self::getCollection('/requestlogs', 'RequestLog');
    }

    public static function getStore()
    {
        $response = self::connection()->get(self::$apiPath . '/store');
        return $response;
    }

    /**
     * The number of requests remaining at the current time. Based on the
     * last request that was fetched within the current script. If no
     * requests have been made, pings the time endpoint to get the value.
     *
     * @return int|false
     */
    public static function getRequestsRemaining()
    {
        $limit = self::connection()->getHeader('X-Rate-Limit-Requests-Left');

        if (!$limit) {
            $result = self::getTime();

            if (!$result) {
                return false;
            }

            $limit = self::connection()->getHeader('X-Rate-Limit-Requests-Left');
        }

        return (int)$limit;
    }

    /**
     * Get a single shipment by given id.
     *
     * @param $orderID
     * @param $shipmentID
     *
     * @return mixed
     */
    public static function getShipment($orderID, $shipmentID)
    {
        return self::getResource('/orders/' . $orderID . '/shipments/' . $shipmentID, 'Shipment');
    }

    /**
     * Get shipments for a given order
     *
     * @param       $orderID
     * @param array $filter
     *
     * @return mixed
     */
    public static function getShipments($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/shipments' . $filter->toQuery(), 'Shipment');
    }

    /**
     * Create shipment
     *
     * @param $orderID
     * @param $object
     *
     * @return mixed
     */
    public static function createShipment($orderID, $object)
    {
        return self::createResource('/orders/' . $orderID . '/shipments', $object);
    }

    /**
     * Update shipment
     *
     * @param $orderID
     * @param $shipmentID
     * @param $object
     *
     * @return mixed
     */
    public static function updateShipment($orderID, $shipmentID, $object)
    {
        return self::updateResource('/orders/' . $orderID . '/shipments/' . $shipmentID, $object);
    }

    /**
     * Delete the given shipment.
     *
     * @param $orderID
     * @param $shipmentID
     *
     * @return mixed
     */
    public static function deleteShipment($orderID, $shipmentID)
    {
        return self::deleteResource('/orders/' . $orderID . '/shipments/' . $shipmentID);
    }

    /**
     * Delete all Shipments for the given order.
     *
     * @param $orderID
     *
     * @return mixed
     */
    public static function deleteAllShipmentsForOrder($orderID)
    {
        return self::deleteResource('/orders/' . $orderID . '/shipments');
    }

    /**
     * Get order coupons for a given order
     *
     * @param       $orderID
     * @param array $filter
     *
     * @return array<OrderCoupons>
     */
    public static function getOrderCoupons($orderID, array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/coupons' . $filter->toQuery(), 'OrderCoupons', legacy: true);
    }

    /**
     * Get a single order shipping address by given order and order shipping address id.
     *
     * @param $orderID
     * @param $orderShippingAddressID
     *
     * @return mixed
     */
    public static function getOrderShippingAddress($orderID, $orderShippingAddressID)
    {
        return self::getResource('/orders/' . $orderID . '/shipping_addresses/' . $orderShippingAddressID, 'Address');
    }

    /**
     * Get order shipping addresses for a given order
     *
     * @param       $orderID
     * @param array $filter
     *
     * @return array<Address>
     */
    public static function getOrderShippingAddresses(int $orderID, array $filter = []): array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/shipping_addresses' . $filter->toQuery(), 'Address', legacy: true);
    }

    /**
     * Create a new currency.
     *
     * @param mixed $object fields to create
     *
     * @return mixed
     */
    public static function createCurrency($object)
    {
        return self::createResource('/currencies', $object);
    }

    /**
     * Returns a single currency resource by the given id.
     *
     * @param int $id currency id
     *
     * @return Resources\Currency|string
     */
    public static function getCurrency($id)
    {
        return self::getResource('/currencies/' . $id, 'Currency');
    }

    /**
     * Update the given currency.
     *
     * @param int $id currency id
     * @param mixed $object fields to update
     *
     * @return mixed
     */
    public static function updateCurrency($id, $object)
    {
        return self::updateResource('/currencies/' . $id, $object);
    }

    /**
     * Delete the given currency.
     *
     * @param int $id currency id
     *
     * @return mixed
     */
    public static function deleteCurrency($id)
    {
        return self::deleteResource('/currencies/' . $id);
    }

    /**
     * Returns the default collection of currencies.
     *
     * @param array $filter
     *
     * @return mixed array|string list of currencies or XML string if useXml is true
     */
    public static function getCurrencies($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/currencies' . $filter->toQuery(), 'Currency');
    }

    /**
     * Create a new product image.
     *
     * @param int $productId
     * @param array $object
     *
     * @return ProductImage
     */
    public static function createProductImage(int $productId, array $object): ProductImage
    {
        return self::createResource('/catalog/products/' . $productId . '/images', $object, 'ProductImage');
    }

    /**
     * Update a product image.
     *
     * @param string $productId
     * @param string $imageId
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateProductImage($productId, $imageId, $object)
    {
        return self::updateResource('/products/' . $productId . '/images/' . $imageId, $object);
    }

    /************************************************************************************************
     * PRODUCT VARIANTS
     ***********************************************************************************************/

    /**
     * Returns all the product variants for the given product.
     *
     * @param int $productId
     *
     * @return array
     */
    public static function getProductVariants(int $productId): array
    {
        return self::getCollection('/catalog/products/' . $productId . '/variants', 'ProductVariant');
    }

    /**
     * Get a product variant by id
     *
     * @param int $productId
     * @param int $variantId
     *
     * @return \Bigcommerce\Api\Resources\ProductVariant
     */
    public static function getProductVariant(int $productId, int $variantId): ProductVariant
    {
        return self::getResource('/catalog/products/' . $productId . '/variants/' . $variantId, 'ProductVariant');
    }

    /**
     * Create a new product variant
     *
     * @param int $productId
     * @param array $productVariantFields
     *
     * @return \Bigcommerce\Api\Resources\ProductVariant
     * @throws \Exception
     */
    public static function createProductVariant(int $productId, array $productVariantFields): ProductVariant
    {
        return self::createResource('/catalog/products/' . $productId . '/variants', $productVariantFields, 'ProductVariant');
    }

    /**
     * Update a product variant
     *
     * @param int $productId
     * @param int $variantId
     * @param array $productVariantFields
     *
     * @return \Bigcommerce\Api\Resources\ProductVariant
     * @throws \Exception
     */
    public static function updateProductVariant(int $productId, int $variantId, array $productVariantFields): ProductVariant
    {
        return self::updateResource('/catalog/products/' . $productId . '/variants/' . $variantId, $productVariantFields, 'ProductVariant');
    }

    /**
     * Update an existing product option
     *
     * @param int $productId
     * @param int $productOptionId
     * @param array $productOptionFields
     *
     * @return \Bigcommerce\Api\Resources\Option
     * @throws \Exception
     */
    public static function updateProductOption(int $productId, int $productOptionId, array $productOptionFields): Option
    {
        return self::updateResource('/catalog/products/' . $productId . '/options/' . $productOptionId, $productOptionFields, 'Option');
    }

    /**
     * Delete a produt variant
     *
     * @param int $productId
     * @param int $variantId
     *
     * @return void
     */
    public static function deleteProductVariant(int $productId, int $variantId): void
    {
        self::deleteResource('/catalog/products/' . $productId . '/variants/' . $variantId);
    }

    /**
     * Returns a product image resource by the given product id.
     *
     * @param int $productId
     * @param int $imageId
     *
     * @return Resources\ProductImage
     */
    public static function getProductImage(int $productId, int $imageId): ProductImage
    {
        return self::getResource('/catalog/products/' . $productId . '/images/' . $imageId, 'ProductImage');
    }

    /**
     * Delete the given product image.
     *
     * @param int $productId
     * @param int $imageId
     *
     * @return void
     */
    public static function deleteProductImage(int $productId, int $imageId): void
    {
        self::deleteResource('/catalog/products/' . $productId . '/images/' . $imageId);
    }

    /**
     * Get all content pages
     *
     * @return mixed
     */
    public static function getPages()
    {
        return self::getCollection('/pages', 'Page');
    }

    /**
     * Get single content pages
     *
     * @param int $pageId
     *
     * @return mixed
     */
    public static function getPage($pageId)
    {
        return self::getResource('/pages/' . $pageId, 'Page');
    }

    /**
     * Create a new content pages
     *
     * @param $object
     *
     * @return mixed
     */
    public static function createPage($object)
    {
        return self::createResource('/pages', $object);
    }

    /**
     * Update an existing content page
     *
     * @param int $pageId
     * @param     $object
     *
     * @return mixed
     */
    public static function updatePage($pageId, $object)
    {
        return self::updateResource('/pages/' . $pageId, $object);
    }

    /**
     * Delete an existing content page
     *
     * @param int $pageId
     *
     * @return mixed
     */
    public static function deletePage($pageId)
    {
        return self::deleteResource('/pages/' . $pageId);
    }

    /**
     * Create a Gift Certificate
     *
     * @param array $object
     *
     * @return mixed
     */
    public static function createGiftCertificate($object)
    {
        return self::createResource('/gift_certificates', $object);
    }

    /**
     * Get a Gift Certificate
     *
     * @param int $giftCertificateId
     *
     * @return mixed
     */
    public static function getGiftCertificate($giftCertificateId)
    {
        return self::getResource('/gift_certificates/' . $giftCertificateId);
    }

    /**
     * Return the collection of all gift certificates.
     *
     * @param array $filter
     *
     * @return mixed
     */
    public static function getGiftCertificates($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/gift_certificates' . $filter->toQuery());
    }

    /**
     * Update a Gift Certificate
     *
     * @param int $giftCertificateId
     * @param array $object
     *
     * @return mixed
     */
    public static function updateGiftCertificate($giftCertificateId, $object)
    {
        return self::updateResource('/gift_certificates/' . $giftCertificateId, $object);
    }

    /**
     * Delete a Gift Certificate
     *
     * @param int $giftCertificateId
     *
     * @return mixed
     */
    public static function deleteGiftCertificate($giftCertificateId)
    {
        return self::deleteResource('/gift_certificates/' . $giftCertificateId);
    }

    /**
     * Delete all Gift Certificates
     *
     * @return mixed
     */
    public static function deleteAllGiftCertificates()
    {
        return self::deleteResource('/gift_certificates');
    }

    /**
     * Create Product Review
     *
     * @param int $productId
     * @param array $object
     *
     * @return mixed
     */
    public static function createProductReview($productId, $object)
    {
        return self::createResource('/products/' . $productId . '/reviews', $object);
    }

    /**
     * Create Product Bulk Discount rules
     *
     * @param string $productId
     * @param array $object
     *
     * @return mixed
     */
    public static function createProductBulkPricingRules($productId, $object)
    {
        return self::createResource('/products/' . $productId . '/discount_rules', $object);
    }

    /**
     * Create a Marketing Banner
     *
     * @param array $object
     *
     * @return mixed
     */
    public static function createMarketingBanner($object)
    {
        return self::createResource('/banners', $object);
    }

    /**
     * Get all Marketing Banners
     *
     * @return mixed
     */
    public static function getMarketingBanners()
    {
        return self::getCollection('/banners');
    }

    /**
     * Delete all Marketing Banners
     *
     * @return mixed
     */
    public static function deleteAllMarketingBanners()
    {
        return self::deleteResource('/banners');
    }

    /**
     * Delete a specific Marketing Banner
     *
     * @param int $bannerID
     *
     * @return mixed
     */
    public static function deleteMarketingBanner($bannerID)
    {
        return self::deleteResource('/banners/' . $bannerID);
    }

    /**
     * Update an existing banner
     *
     * @param int $bannerID
     * @param array $object
     *
     * @return mixed
     */
    public static function updateMarketingBanner($bannerID, $object)
    {
        return self::updateResource('/banners/' . $bannerID, $object);
    }

    /**
     * Add a address to the customer's address book.
     *
     * @param int $customerID
     * @param array $object
     *
     * @return mixed
     */
    public static function createCustomerAddress($customerID, $object)
    {
        return self::createResource('/customers/' . $customerID . '/addresses', $object);
    }

    /**
     * @param $cuatomerID
     * @param $attributeID
     * @param $object
     *
     * @return mixed
     */
    public static function upsertCustomerAttributeValue($customerID, $attributeID, $value)
    {

        $object = [
            'customer_id' => $customerID,
            'attribute_id' => $attributeID,
            'value' => $value,
        ];

        return self::connection()->put(self::$apiPath_v3 . '/customers/attribute-values', [$object]);
    }

    /**
     * Create a product rule
     *
     * @param int $productID
     * @param array $object
     *
     * @return mixed
     */
    public static function createProductRule($productID, $object)
    {
        return self::createResource('/products/' . $productID . '/rules', $object);
    }

    /**
     * Create a customer group.
     *
     * @param array $object
     *
     * @return mixed
     */
    public static function createCustomerGroup($object)
    {
        return self::createResource('/customer_groups', $object);
    }

    /**
     * Get list of customer groups
     *
     * @return mixed
     */
    public static function getCustomerGroups(): Collection
    {
        return self::getCollection('/customer_groups');
    }

    /**
     * Get a customer group by id
     *
     * @param int $id
     * @legacy
     * @return \Bigcommerce\Api\Resources\CustomerGroup
     * @throws \Bigcommerce\Api\Exceptions\ClientException
     * @throws \Bigcommerce\Api\Exceptions\ServerException
     */
    public static function getCustomerGroup(int $id): CustomerGroup
    {
        return self::getResource('/customer_groups/' . $id, 'CustomerGroup', true);
    }

    public static function updateCustomerGroup(int $id, array $object): CustomerGroup
    {
        return self::updateResource('/customer_groups/' . $id, $object, 'CustomerGroup', true);
    }

    /**
     * Delete a customer group
     *
     * @param int $customerGroupId
     *
     * @return mixed
     */
    public static function deleteCustomerGroup($customerGroupId)
    {
        return self::deleteResource('/customer_groups/' . $customerGroupId);
    }

    /**
     * Delete all options
     *
     * @return mixed
     */
    public static function deleteAllOptions()
    {
        return self::deleteResource('/options');
    }

    /**
     * Return the collection of all option values for a given option.
     *
     * @param int $productId
     *
     * @return mixed
     */
    public static function getProductOptions($productId)
    {
        return self::getCollection('/catalog/products/' . $productId . '/options', "ProductOption");
    }

    public static function setProductOptions($productId, $optionId, $object, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::updateResource($subpath . $productId . '/options/' . $optionId, $object, $v);
    }


    /**
     * Create a product option
     *
     * @param int $productId
     * @param string $name
     * @param string $type
     * @param string $displayName
     * @param array $optionValues
     *
     * @return ProductOption
     */
    public static function createProductOption(int $productId, array $optionData): ProductOption
    {
        return self::createResource('/catalog/products/' . $productId . '/options', $optionData, 'ProductOption');
    }

    /**
     * Return the collection of all option values for a given option.
     *
     * @param int $productId
     * @param int $productOptionId
     *
     * @return mixed
     */
    public static function getProductOption($productId, $productOptionId): ProductOption
    {
        return self::getResource('/catalog/products/' . $productId . '/options/' . $productOptionId);
    }

    /**
     * Return the collection of all option values for a given option.
     *
     * @param int $productId
     * @param int $productRuleId
     *
     * @return mixed
     */
    public static function getProductRule($productId, $productRuleId)
    {
        return self::getResource('/products/' . $productId . '/rules/' . $productRuleId);
    }

    /**
     * Return the option value object that was created.
     *
     * @param int $optionId
     * @param array $object
     *
     * @return mixed
     */
    public static function createOptionValue($optionId, $object)
    {
        return self::createResource('/options/' . $optionId . '/values', $object);
    }

    /**
     * Delete all option sets that were created.
     *
     * @return mixed
     */
    public static function deleteAllOptionSets()
    {
        return self::deleteResource('/optionsets');
    }

    /**
     * Return the option value object that was updated.
     *
     * @param int $optionId
     * @param int $optionValueId
     * @param array $object
     *
     * @return mixed
     */
    public static function updateOptionValue($optionId, $optionValueId, $object): OptionValue
    {
        return self::updateResource(
            '/options/' . $optionId . '/values/' . $optionValueId,
            $object
        );
    }

    /**
     * Create a product option value
     *
     * @param int $productId
     * @param int $optionId
     * @param int $optionValueId
     * @param array $optionValueFields
     *
     * @return \Bigcommerce\Api\Resources\OptionValue
     * @throws \Exception
     */
    public static function updateProductOptionValue(int $productId, int $optionId, int $optionValueId, array $optionValueFields): OptionValue
    {
        return self::updateResource('/catalog/products/' . $productId . '/options/' . $optionId . '/values/' . $optionValueId, $optionValueFields, 'OptionValue');
    }

    public static function createProductOptionValue(int $productId, int $optionId, array $optionValueFields): OptionValue
    {
        return self::createResource('/catalog/products/' . $productId . '/options/' . $optionId . '/values', $optionValueFields, 'OptionValue');
    }

    /**
     * Returns all webhooks.
     *
     * @return mixed Resource|string resource object or XML string if useXml is true
     */
    public static function listWebhooks()
    {
        return self::getCollection('/hooks');
    }

    /**
     * Returns data for a specific web-hook.
     *
     * @param int $id
     *
     * @return mixed Resource|string resource object or XML string if useXml is true
     */
    public static function getWebhook($id)
    {
        return self::getResource('/hooks/' . $id);
    }

    /**
     * Creates a web-hook.
     *
     * @param mixed $object object or XML string to create
     *
     * @return mixed
     */
    public static function createWebhook($object)
    {
        return self::createResource('/hooks', $object);
    }

    /**
     * Updates the given webhook.
     *
     * @param int $id
     * @param mixed $object object or XML string to create
     *
     * @return mixed
     */
    public static function updateWebhook($id, $object)
    {
        return self::updateResource('/hooks/' . $id, $object);
    }

    /**
     * Delete the given webhook.
     *
     * @param int $id
     *
     * @return mixed
     */
    public static function deleteWebhook($id)
    {
        return self::deleteResource('/hooks/' . $id);
    }

    /**
     * Return a collection of shipping-zones
     *
     * @return mixed
     */
    public static function getShippingZones()
    {
        return self::getCollection('/shipping/zones', 'ShippingZone');
    }

    /**
     * Return a shipping-zone by id
     *
     * @param int $id shipping-zone id
     *
     * @return mixed
     */
    public static function getShippingZone($id)
    {
        return self::getResource('/shipping/zones/' . $id, 'ShippingZone');
    }


    /**
     * Delete the given shipping-zone
     *
     * @param int $id shipping-zone id
     *
     * @return mixed
     */
    public static function deleteShippingZone($id)
    {
        return self::deleteResource('/shipping/zones/' . $id);
    }

    /**
     * Return a shipping-method by id
     *
     * @param $zoneId
     * @param $methodId
     *
     * @return mixed
     */
    public static function getShippingMethod($zoneId, $methodId)
    {
        return self::getResource('/shipping/zones/' . $zoneId . '/methods/' . $methodId, 'ShippingMethod');
    }

    /**
     * Return a collection of shipping-methods
     *
     * @param $zoneId
     *
     * @return mixed
     */
    public static function getShippingMethods($zoneId)
    {
        return self::getCollection('/shipping/zones/' . $zoneId . '/methods', 'ShippingMethod');
    }


    /**
     * Delete the given shipping-method by id
     *
     * @param $zoneId
     * @param $methodId
     *
     * @return mixed
     */
    public static function deleteShippingMethod($zoneId, $methodId)
    {
        return self::deleteResource('/shipping/zones/' . $zoneId . '/methods/' . $methodId);
    }

    /**
     * Get collection of product skus by Product
     *
     * @param       $productId
     * @param array $filter
     *
     * @return mixed
     */
    public static function getSkusByProduct($productId, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products/' . $productId . '/skus' . $filter->toQuery(), 'Sku');
    }

    /**
     * Delete the given optionValue.
     *
     * @param int $optionId
     *
     * @Param int $valueId
     * @return mixed
     */
    public static function deleteOptionValue($optionId, $valueId)
    {
        return self::deleteResource('/options/' . $optionId . '/values/' . $valueId);
    }

    /**
     * Return the collection of all option values By OptionID
     *
     * @param int $optionId
     * @param array $filter
     *
     * @return array
     */
    public static function getOptionValuesByOption($optionId, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/options/' . $optionId . '/values' . $filter->toQuery(), 'OptionValue');
    }

    /**
     * Get collection of product rules by ProductId
     *
     * @param int $productId
     * @param array $filter
     *
     * @return mixed
     */
    public static function getRulesByProduct($productId, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products/' . $productId . '/rules' . $filter->toQuery(), 'Rule');
    }

    /**
     * ************************************************
     * CARTS
     * ************************************************
     */

    /**
     * Get a Cart by the given BigCommerce ID
     *
     * @param string $cartId
     * @return mixed
     */
    public static function getCart(string $cartId): mixed
    {
        try {
            return static::getResource('/carts/' . $cartId);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Create a Cart
     *
     * @param array $params
     */
    public static function createCart(array $params)
    {
        return static::createResource('/carts', $params, 'Resource');
    }

    /**
     * Delete a Cart by the given BigCommerce ID
     *
     * @param string $cartId
     */
    public static function deleteCart(string $cartId)
    {
        static::deleteResource('/carts/' . $cartId);
    }

    /**
     * ************************************************
     * CARTS LINE ITEMS
     * ************************************************
     */

    /**
     * Adds line item to the Cart
     *
     * @param string $cartId
     * @param array $params
     */
    public static function addCartLineItems(string $cartId, array $params)
    {
        return static::createResource('/carts/' . $cartId . '/items', $params, 'Resource');
    }

    /**
     * Updates an existing, single line item in the Cart
     *
     * @param string $cartId
     * @param string $itemId
     * @param array $params
     */
    public static function updateCartLineItems(string $cartId, string $itemId, array $params)
    {
        return static::updateResource('/carts/' . $cartId . '/items/' . $itemId, $params, 'Resource');
    }

    /**
     * Deletes a Cart line item.
     * Removing the last line_item in the Cart deletes the Cart
     *
     * @param string $cartId
     * @param string $itemId
     */
    public static function deleteCartLineItems(string $cartId, string $itemId)
    {
        return static::deleteResource('/carts/' . $cartId . '/items/' . $itemId, 'Resource');
    }


    /**
     * WIDGETS
     */

    /**
     * Get all widgets
     *
     * @param array $filter
     * @return Resource[]
     */
    public static function getWidgets(array $filter = []): array
    {
        $filter = Filter::create($filter);

        return self::getCollection('/content/widgets' . $filter->toQuery());
    }

    /**
     * Get a widget by the given uuid
     *
     * @param string $uuid
     * @return Resource
     */
    public static function getWidget(string $uuid): Resource
    {
        return self::getResource('/content/widgets/' . $uuid);
    }
}

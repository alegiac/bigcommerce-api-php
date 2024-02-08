<?php

namespace Bigcommerce\Api;

use Bigcommerce\Api\Exceptions\ClientException;
use Bigcommerce\Api\Resources\ResourceEnum;
use DateTime;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use phpDocumentor\Reflection\Types\Self_;

/**
 * Bigcommerce API Client.
 */
class NewClient
{
    const OAUTH_MODE = 'OAUTH';
    const BASIC_AUTH_MODE = 'BASIC_AUTH';

    /**
     * @var string $connectionMode The connection mode to use
     * @static
     */
    private static string $connectionMode = self::OAUTH_MODE;

    private static bool $useXml = false;
    private static bool $failOnError = false;

    /**
     * @var string $storeUrl URL to the store API
     * @static
     */
    private static string $storeUrl;

    private static string $username;

    /**
     * API key
     *
     * @var string
     */
    private static string $apiKey;

    /**
     * @var \Bigcommerce\Api\Connection|null $connection The HTTP connection object
     * @static
     */
    private static ?Connection $connection;

    /**
     * Resource class name
     *
     * @var string
     */
    private static string $resource;

    private static string $pathPrefix = 'api/v3';

    /**
     * Full URL path to the configured store API.
     *
     * @var string
     */
    public static $apiPath;
    public static $legacyApiPath;

    /** @var string The OAuth client ID */
    private static $clientId;
    /** @var string The store hash */
    private static $storeHash;
    /** @var string The OAuth Auth-Token */
    private static $authToken;
    /** @var string */
    private static $clientSecret;
    /** @var string URL pathname prefix for the V2 API */
    private static $storesPrefix = '/stores/%s/v3';
    private static $legacyStoresPrefix = '/stores/%s/v2';

    /** @var string The BigCommerce store management API host */
    private static $apiUrl = 'https://api.bigcommerce.com';
    /** @var string The BigCommerce merchant login URL */
    private static $loginUrl = 'https://login.bigcommerce.com';

    /**
     * Configure the API client with the required Oauth/BasicAuth credentials.
     *
     * @param array  $settings
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
        if (!isset($settings['store_hash'])) throw new ClientException( 'NewClient configureOauth: store_hash must be provided');
        if (!isset($settings['client_id'])) throw new ClientException('NewClient configureOauth: client_id must be provided');

        self::$clientSecret = $settings['client_secret'] ?? null;
        self::$apiPath = self::$apiUrl . sprintf(self::$storesPrefix, self::$storeHash);
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
    public static function configureBasicAuth(array $settings):void
    {
        if (!isset($settings['store_url'])) throw new ClientException('NewClient configureBasicAuth: store_url must be provided');
        if (!isset($settings['username'])) throw new ClientException('NewClient configureBasicAuth: username must be provided');
        if (!isset($settings['api_key'])) throw new ClientException('NewClient configureBasicAuth: api_key must be provided');

        self::$username = $settings['username'];
        self::$apiKey = $settings['api_key'];
        self::$storeUrl = rtrim($settings['store_url'], '/');
        self::$apiPath = self::$storeUrl . self::$pathPrefix;

    }

    /**
     * Configure base settings for the API client.
     * Requires a settings array to be passed in with the following
     * keys:
     * - use_xml
     * - fail_on_error
     * - connection_mode
     *
     * @param array $settings
     *
     * @return void
     */
    private static function configureBaseSettings(array $settings): void
    {
        // NOTE: useXml is out of scope in ES

        //self::$useXml = isset($settings['uee_xml']) && $settings['use_xml'] === true;
        self::$failOnError = isset($settings['fail_on_error']) && $settings['fail_on_error'] === true;
        self::$connectionMode = $settings['connection_mode'] ?? self::OAUTH_MODE;
    }

    /**
     * Switch SSL certificate verification on requests.
     *
     * @param bool $option sets the value of this flag
     *
     * @return void
     */
    public static function verifyPeer(bool $option = false): void
    {
        self::connection()->verifyPeer($option);
    }

    /**
     * Connect to the internet through a proxy server.
     *
     * @param string   $host host server
     * @param int|bool $port port number to use, or false
     *
     * @return void
     */
    public static function useProxy(string $host, bool $port = false): void
    {
        self::connection()->useProxy($host, $port);
    }

    /**
     * Get error message returned from the last API request if
     * failOnError is false (default).
     *
     * @return string
     */
    public static function getLastError()
    {
        if ($error = self::connection()->getLastError()) {
            return $error;
        }
        /*
        $errorData = json_decode(json_encode(self::connection()->getLastError()), true);
        return $errorData['message'] . "(" . implode(",", $errorData['errors']) . ")";
        */

        return false;
    }

    /**
     * Get an instance of the HTTP connection object. Initializes
     * the connection if it is not already active.
     *
     * @return Connection
     */
    private static function connection(): Connection
    {
        // If the connection is not already initialized, create, authorize and configure it.
        if (!self::$connection) {

            self::$connection = new Connection();
            self::$connection->useXml(false );
            self::$connection->failOnError(self::$failOnError);

            self::$connectionMode === self::OAUTH_MODE ?
                self::$connection->authenticateOauth(self::$clientId, self::$authToken)
                :
                self::$connection->authenticateBasic(self::$username, self::$apiKey);
        }

        return self::$connection;
    }

    /**
     * Convenience method to return instance of the connection
     *
     * @return Connection
     */
    public static function getConnection(): Connection
    {
        return self::connection();
    }

    /**
     * Get a collection result from the specified endpoint.
     *
     * @param string                                  $pathWithLeadingSlash
     * @param \Bigcommerce\Api\Resources\ResourceEnum $resource resource class to map individual items
     * @param bool                                    $legacy
     *
     * @return array|string array|string mapped collection or XML string if useXml is true
     */
    public static function getCollection(string $pathWithLeadingSlash, ResourceEnum $resource, bool $legacy = false):array|string
    {
        $response = self::connection()->get(self::$apiPath . $pathWithLeadingSlash);
        return self::mapCollection($resource, $response, $legacy);
    }

    /**
     * Get a resource entity from the specified endpoint.
     *
     * @param string $path api endpoint
     * @param string $resource resource class to map individual items
     *
     * @return mixed Resource|string resource object or XML string if useXml is true
     */
    public static function getResource(string $path, string $resource = 'Resource', $legacy = false): mixed
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
     * @return string|int|bool int|string count value or XML string if useXml is true
     */
    public static function getCount(string $path, $legacy = false): string|int|bool
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
     * @throws \Exception If the request fails and failOnError is true
     */
    public static function createResource(string $path, array $object, bool $legacy = false): Resource
    {
        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $post = self::connection()->get($composedPath, $object);
        return self::mapResource(self::$resource, $post);
    }

    /**
     * Send a put request to update the specified resource.
     *
     * @param string $path api endpoint
     * @param array  $object object or XML string to update
     * @param bool   $legacy
     *
     * @return \Bigcommerce\Api\Resource|null
     * @throws  \Exception If the request fails and failOnError is true
     */
    public static function updateResource(string $path, array $object, bool $legacy = false): Resource|null
    {
        $composedPath = $legacy ? self::$legacyApiPath . $path : self::$apiPath . $path;
        $put = self::connection()->put($composedPath, $object);
        return self::mapResource(self::$resource, $put);
    }

    /**
     * Send a delete request to remove the specified resource.
     *
     * @param string $path api endpoint
     *
     * @return mixed
     */
    public static function deleteResource(string $path)
    {
        return self::connection()->delete((self::$apiPath ) . $path);
    }

    /**
     * Internal method to wrap items in a collection to resource classes.
     *
     * @param string $resource name of the resource class
     * @param mixed  $object object collection
     * @param bool   $legacy
     *
     * @return array|null
     */
    private static function mapCollection(string $resource, ?array $object, bool $legacy = false): array|null
    {
        if (!is_array($object)) return null;
        if ($legacy !== true && isset($object->data)) $object = $object->data;

        $baseResource = __NAMESPACE__ . '\\' . $resource;
        self::$resource = ( class_exists($baseResource) ) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;
        return array_map(array(self::class, 'mapCollectionObject'), $object);
    }

    /**
     * Callback for mapping collection objects resource classes.
     *
     * @param \stdClass $object
     *
     * @return Resource
     */
    private static function mapCollectionObject($object)
    {
        $class = self::$resource;

        return new $class($object);
    }

    /**
     * Map a single object to a resource class.
     *
     * @param string    $resource name of the resource class
     * @param \stdClass $object
     *
     * @return Resource
     */
    private static function mapResource(string $resource, ?array $object):Resource|null
    {
        if (!is_array($object)) return null;

        $baseResource = __NAMESPACE__ . '\\' . $resource;
        $class = ( class_exists($baseResource) ) ? $baseResource : 'Bigcommerce\\Api\\Resources\\' . $resource;
        return new $class($object);
    }

    /**
     * Map object representing a count to an integer value.
     *
     * @param \stdClass $object
     *
     * @return int|false
     */
    private static function mapCount(?array $object):int|false
    {
        if (!is_array($object)) return false;
        return $object['count'];
    }

    /**
     * Pings the time endpoint to test the connection to a store.
     *
     * @return ?DateTime
     */
    public static function getTime():DateTime|null
    {
        $response = self::connection()->get(self::$apiUrl . '/time');
        if (empty($response)) return null;
        return new DateTime("@{$response}");
    }

    /**
     * Returns the default collection of products.
     *
     * @param array $filter
     *
     * @return mixed array|string list of products or XML string if useXml is true
     */
    public static function getProducts(array $filter = []):array
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products' . $filter->toQuery(), ResourceEnum::Product);
    }

    public static function getLocations($filter = [])
    {
        $filter = Filter::create($filter);
        return self::getCollection('/inventory/locations', 'Location', 'V3');
    }

    /**
     * Create a new location.
     *
     * @param mixed $object fields to create
     *
     * @return mixed
     */
    public static function createLocation($object)
    {
        return self::createResource('/inventory/locations', $object, 'V3');
    }

    /**
     * Update the given product.
     *
     * @param int   $id product id
     * @param mixed $object fields to update
     *
     * @return mixed
     */
    public static function updateLocation($id, $object)
    {
        return self::updateResource('/inventory/locations/' . $id, $object, 'V3');
    }

    /**
     * Gets collection of images for a product.
     *
     * @param int $id product id
     *
     * @return mixed array|string list of products or XML string if useXml is true
     */
    public static function getProductImages($id, $v = 'V2')
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::getCollection($subpath . $id . '/images', 'ProductImage', $v);
    }

    /**
     * Gets collection of custom fields for a product.
     *
     * @param int $id product ID
     *
     * @return array|string list of products or XML string if useXml is true
     */
    public static function getProductCustomFields($id)
    {
        return self::getCollection('/products/' . $id . '/custom_fields', 'ProductCustomField');
    }

    /**
     * Returns a single custom field by given id
     *
     * @param int $product_id product id
     * @param int $id custom field id
     *
     * @return Resources\ProductCustomField|bool Returns ProductCustomField if exists, false if not exists
     */
    public static function getProductCustomField($product_id, $id)
    {
        return self::getResource('/products/' . $product_id . '/custom_fields/' . $id, 'ProductCustomField');
    }

    /**
     * Create a new custom field for a given product.
     *
     * @param int   $product_id product id
     * @param mixed $object fields to create
     *
     * @return Object Object with `id`, `product_id`, `name` and `text` keys
     */
    public static function createProductCustomField($product_id, $object)
    {
        return self::createResource('/products/' . $product_id . '/custom_fields', $object);
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
     * @param int   $product_id product id
     * @param int   $id custom field id
     * @param mixed $object custom field to update
     *
     * @return mixed
     */
    public static function updateProductCustomField($product_id, $id, $object)
    {
        return self::updateResource('/products/' . $product_id . '/custom_fields/' . $id, $object);
    }

    /**
     * Delete the given custom field.
     *
     * @param int $product_id product id
     * @param int $id custom field id
     *
     * @return mixed
     */
    public static function deleteProductCustomField($product_id, $id)
    {
        return self::deleteResource('/products/' . $product_id . '/custom_fields/' . $id);
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

    /**
     * Returns a single product resource by the given id.
     *
     * @param int $id product id
     *
     * @return Resources\Product|string
     */
    public static function getProduct($id)
    {
        return self::getResource('/products/' . $id, 'Product');
    }

    /**
     * Create a new product.
     *
     * @param array $productFields Product fields
     *
     * @return \Bigcommerce\Api\Resources\Product
     */
    public static function createProduct(array $productFields):Product
    {
        return self::createResource('/products', $productFields);
    }

    /**
     * Update the given product.
     *
     * @param int   $id product id
     * @param mixed $object fields to update
     *
     * @return mixed
     */
    public static function updateProduct($id, $object, $v = 'V2')
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::updateResource($subpath . $id, $object, $v);
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
    public static function assignProductToChannelId($productId, $channelId, $v = "V2")
    {
        $object = [
            'product_id' => $productId,
            'channel_id' => $channelId,
        ];

        $subpath = $v === "V2" ? '/products/' : '/catalog/products/channel-assignments';

        return self::connection()->put(( self::$apiPath_v3 ) . $subpath, [$object]);


        //return self::updateResource($subpath, $object, $v);
    }

    public static function assignLayoutToProductInChannelId($productId, $channelId, $layoutFile)
    {
        $object = new \stdClass();
        $object->entity_type = 'product';
        $object->entity_id = $productId;
        $object->channel_id = $channelId;
        $object->file_name = $layoutFile;

        return self::connection()->put(self::$apiPath_v3 . '/storefront/custom-template-associations', [$object]);
    }


    /**
     * Delete the given product.
     *
     * @param int $id product id
     *
     * @return mixed
     */
    public static function deleteProduct($id)
    {
        return self::deleteResource('/products/' . $id);
    }

    /**
     * Delete all products.
     *
     * @return mixed
     */
    public static function deleteAllProducts()
    {
        return self::deleteResource('/products');
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
     * @param int   $id category id
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
     * The collection of categories.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getCategories($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/categories' . $filter->toQuery(), 'Category');
    }

    /**
     * The number of categories in the collection.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function getCategoriesCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/categories/count' . $filter->toQuery());
    }

    /**
     * A single category by given id.
     *
     * @param int $id category id
     *
     * @return Resources\Category
     */
    public static function getCategory($id)
    {
        return self::getResource('/categories/' . $id, 'Category');
    }

    /**
     * Create a new category from the given data.
     *
     * @param mixed $object
     *
     * @return mixed
     */
    public static function createCategory($object)
    {
        return self::createResource('/categories', $object);
    }

    /**
     * Update the given category.
     *
     * @param int   $id category id
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateCategory($id, $object)
    {
        return self::updateResource('/categories/' . $id, $object);
    }

    /**
     * Delete the given category.
     *
     * @param int $id category id
     *
     * @return mixed
     */
    public static function deleteCategory($id)
    {
        return self::deleteResource('/categories/' . $id);
    }

    /**
     * Delete all categories.
     *
     * @return mixed
     */
    public static function deleteAllCategories()
    {
        return self::deleteResource('/categories');
    }

    /**
     * The collection of brands.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getBrands($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/brands' . $filter->toQuery(), 'Brand');
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
     * @return Resources\Brand
     */
    public static function getBrand($id)
    {
        return self::getResource('/brands/' . $id, 'Brand');
    }

    /**
     * Create a new brand from the given data.
     *
     * @param mixed $object
     *
     * @return mixed
     */
    public static function createBrand($object)
    {
        return self::createResource('/brands', $object);
    }

    /**
     * Update the given brand.
     *
     * @param int   $id brand id
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateBrand($id, $object)
    {
        return self::updateResource('/brands/' . $id, $object);
    }

    /**
     * Delete the given brand.
     *
     * @param int $id brand id
     *
     * @return mixed
     */
    public static function deleteBrand($id)
    {
        return self::deleteResource('/brands/' . $id);
    }

    /**
     * Delete all brands.
     *
     * @return mixed
     */
    public static function deleteAllBrands()
    {
        return self::deleteResource('/brands');
    }

    /**
     * The collection of orders.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getOrders($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders' . $filter->toQuery(), 'Order');
    }

    /**
     * The number of orders in the collection.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function getOrdersCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/orders/count' . $filter->toQuery());
    }

    /**
     * The order count grouped by order status
     *
     * @param array $filter
     *
     * @return Resources\OrderStatus
     */
    public static function getOrderStatusesWithCounts($filter = array())
    {
        $filter = Filter::create($filter);
        $resource = self::getResource('/orders/count' . $filter->toQuery(), "OrderStatus");
        return $resource->statuses;
    }

    /**
     * A single order.
     *
     * @param int $id order id
     *
     * @return Resources\Order
     */
    public static function getOrder($id)
    {
        return self::getResource('/orders/' . $id, 'Order');
    }

    /**
     * @param $orderID
     *
     * @return mixed
     */
    public static function getOrderProducts($orderID)
    {
        return self::getCollection('/orders/' . $orderID . '/products', 'OrderProduct');
    }

    /**
     * The total number of order products in the collection.
     *
     * @param       $orderID
     * @param array $filter
     *
     * @return mixed
     */
    public static function getOrderProductsCount($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/orders/' . $orderID . '/products/count' . $filter->toQuery());
    }

    /**
     * Delete the given order (unlike in the Control Panel, this will permanently
     * delete the order).
     *
     * @param int $id order id
     *
     * @return mixed
     */
    public static function deleteOrder($id)
    {
        return self::deleteResource('/orders/' . $id);
    }

    /**
     * Delete all orders.
     *
     * @return mixed
     */
    public static function deleteAllOrders()
    {
        return self::deleteResource('/orders');
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
     * @param int   $id order id
     * @param mixed $object fields to update
     *
     * @return mixed
     */
    public static function updateOrder($id, $object)
    {
        return self::updateResource('/orders/' . $id, $object);
    }

    /**
     * The list of customers.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function getCustomers($filter = array())
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
     */
    public static function getCustomersCount($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCount('/customers/count' . $filter->toQuery());
    }

    /**
     * Bulk delete customers.
     *
     * @param array $filter
     *
     * @return array
     */
    public static function deleteCustomers($filter = array())
    {
        $filter = Filter::create($filter);
        return self::deleteResource('/customers' . $filter->toQuery());
    }

    /**
     * A single customer by given id.
     *
     * @param int $id customer id
     *
     * @return Resources\Customer
     */
    public static function getCustomer($id)
    {
        return self::getResource('/customers/' . $id, 'Customer');
    }

    public static function getCustomerAttributes($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/customers/attributes' . $filter->toQuery(), 'CustomerAttribute', 'V3');
    }

    public static function createCustomer($object, $v = "V2")
    {
        $connection = self::connection();
        return $connection->post(self::$apiPath_v3  . '/customers', [$object]);
    }

    public static function createCustomerAttribute($object)
    {
        return self::createResource('/customers/attributes', $object);
    }

    /**
     * Update the given customer.
     *
     * @param int   $id customer id
     * @param mixed $object
     *
     * @return mixed
     */
    public static function updateCustomer($id, $object)
    {
        return self::updateResource('/customers/' . $id, $object);
    }

    /**
     * Delete the given customer.
     *
     * @param int $id customer id
     *
     * @return mixed
     */
    public static function deleteCustomer($id)
    {
        return self::deleteResource('/customers/' . $id);
    }

    /**
     * A list of addresses belonging to the given customer.
     *
     * @param int $id customer id
     *
     * @return array
     */
    public static function getCustomerAddresses($id)
    {
        return self::getCollection('/customers/' . $id . '/addresses', 'Address');
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
     * @param int   $id option set id
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
     * @return mixed
     */
    public static function getOrderCoupons($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/coupons' . $filter->toQuery(), 'OrderCoupons');
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
     * @return mixed
     */
    public static function getOrderShippingAddresses($orderID, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/orders/' . $orderID . '/shipping_addresses' . $filter->toQuery(), 'Address');
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
     * @param int   $id currency id
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
     * @param string $productId
     * @param mixed  $object
     *
     * @return mixed
     */
    public static function createProductImage($productId, $object, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::createResource($subpath . $productId . '/images', $object, $v);
    }

    /**
     * Update a product image.
     *
     * @param string $productId
     * @param string $imageId
     * @param mixed  $object
     *
     * @return mixed
     */
    public static function updateProductImage($productId, $imageId, $object)
    {
        return self::updateResource('/products/' . $productId . '/images/' . $imageId, $object);
    }

    /**
     * Create a product variant
     *
     * @param $productId
     * @param $object
     * @param $v
     *
     * @return mixed
     */
    public static function createProductVariant(int $productId, $object, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::createResource($subpath . $productId . '/variants', $object, $v);
    }

    public static function updateProductVariant($productId, $variantId, $object, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::updateResource($subpath . $productId . '/variants/' . $variantId, $object, $v);
    }

    /**
     * Delete a produt variant
     *
     * @param $productId
     * @param $variantId
     *
     * @return mixed
     */
    public static function deleteProductVariant($productId, $variantId)
    {
        return self::deleteResource('/catalog/products/' . $productId . '/variants/' . $variantId, 'V3');
    }


    public static function getProductVariants($productId)
    {
        return self::getCollection('/catalog/products/' . $productId . '/variants', 'Variant', 'V3');
    }

    /**
     * Returns a product image resource by the given product id.
     *
     * @param int $productId
     * @param int $imageId
     *
     * @return Resources\ProductImage|string
     */
    public static function getProductImage($productId, $imageId)
    {
        return self::getResource('/products/' . $productId . '/images/' . $imageId, 'ProductImage');
    }

    /**
     * Delete the given product image.
     *
     * @param int $productId
     * @param int $imageId
     *
     * @return mixed
     */
    public static function deleteProductImage($productId, $imageId, $v = "V2")
    {

        return self::deleteResource('/products/' . $productId . '/images/' . $imageId);
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
     * @param int   $giftCertificateId
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
     * @param int   $productId
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
     * @param array  $object
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
     * @param int   $bannerID
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
     * @param int   $customerID
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

        return self::connection()->put(self::$apiPath_v3.'/customers/attribute-values',[$object]);
    }

    /**
     * Create a product rule
     *
     * @param int   $productID
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
    public static function getCustomerGroups()
    {
        return self::getCollection('/customer_groups');
    }

    public static function getCustomerGroup($id)
    {
        return self::getResource('/customer_groups/' . $id,'CustomerGroup');
    }

    public static function updateCustomerGroup($id, $object)
    {
        return self::updateResource('/customer_groups/' . $id, $object);
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
     * Delete all customers
     *
     * @return mixed
     */
    public static function deleteAllCustomers()
    {
        return self::deleteResource('/customers');
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
    public static function getProductOptions($productId, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::getCollection($subpath . $productId . '/options', "ProductOption", $v);
    }

    public static function setProductOptions($productId, $optionId, $object, $v = "V2")
    {
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::updateResource($subpath . $productId . '/options/' . $optionId, $object, $v);
    }


    public static function createProductOption(int $productId, string $name, string $type, string $displayName, array $optionValues, $v = "V3")
    {
        $object = [
            'name' => $name,
            'display_name' => $displayName,
            'type' => $type,
            'option_values' => $optionValues,
        ];
        $subpath = $v === "V2" ? '/products/' : '/catalog/products/';
        return self::createResource($subpath . $productId . '/options', $object, $v);
    }


    /**
     * Return the collection of all option values for a given option.
     *
     * @param int $productId
     * @param int $productOptionId
     *
     * @return mixed
     */
    public static function getProductOption($productId, $productOptionId)
    {
        return self::getResource('/products/' . $productId . '/options/' . $productOptionId);
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
     * @param int   $optionId
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
     * @param int   $optionId
     * @param int   $optionValueId
     * @param array $object
     *
     * @return mixed
     */
    public static function updateOptionValue($optionId, $optionValueId, $object)
    {
        return self::updateResource(
            '/options/' . $optionId . '/values/' . $optionValueId,
            $object
        );
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
     * @param int   $id
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
     * @param int   $optionId
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
     * @param int   $productId
     * @param array $filter
     *
     * @return mixed
     */
    public static function getRulesByProduct($productId, $filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/products/' . $productId . '/rules' . $filter->toQuery(), 'Rule');
    }

    static public function createPricelist(array $object)
    {
        return self::createResource('/pricelists', $object, "V3");
    }

    static public function updatePricelist(int $pricelistId, array $object)
    {
        return self::updateResource('/pricelists/' . $pricelistId, $object, "V3");
    }

    /**
     * Returns the default collection of pricelists
     *
     * @param array $filter
     *
     * @return mixed array|string list of pricelists
     */
    public static function getPricelists($filter = array())
    {
        $filter = Filter::create($filter);
        return self::getCollection('/pricelists' . $filter->toQuery(), 'Pricelist','V3');
    }

    public static function upsertPricelistToCustomerGroup($pricelistId,$customerGroupId,$channelId)
    {
        $object = [
            'customer_group_id' => $customerGroupId,
            'channel_id' => $channelId,
        ];

        $subpath = '/pricelists/'.$pricelistId.'/assignments';

        return self::connection()->put(self::$apiPath_v3 . $subpath, $object);

    }

    public static function upsertPricelistRecords($pricelistId, $records)
    {
        $subpath = '/pricelists/'.$pricelistId.'/records';
        return self::connection()->put(self::$apiPath_v3 . $subpath, [$records]);
    }

    static public function deletePricelist(int $pricelistId)
    {
        return self::deleteResource('/pricelists/' . $pricelistId, "V3");
    }

    static public function getAllPricelistRecords(int $pricelistId)
    {
        return self::getCollection('/pricelists/' . $pricelistId . '/records', 'PricelistRecord', "V3");
    }

}

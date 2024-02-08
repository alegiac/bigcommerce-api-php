<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

/**
 * Represents a single pricelist.
 */
class PricelistRecord extends Resource
{
    /**
     * @see https://developer.bigcommerce.com/display/API/Products#Products-ReadOnlyFields
     * @var array
     */
    protected $ignoreOnUpdate = array(
        'id',
    );
}

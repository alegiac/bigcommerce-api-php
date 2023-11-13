<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;

/**
 * Represents a single pricelist.
 */
class Pricelist extends Resource
{
    /**
     * @see https://developer.bigcommerce.com/display/API/Products#Products-ReadOnlyFields
     * @var array
     */
    protected $ignoreOnUpdate = array(
        'id',
    );
}

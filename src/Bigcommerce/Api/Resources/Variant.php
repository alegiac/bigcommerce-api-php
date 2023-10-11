<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

/**
 * A variant for a product.
 */
class Variant extends Resource
{
    /** @var string[] */
    protected $ignoreOnCreate = array(
        'product_id',
    );

    /** @var string[] */
    protected $ignoreOnUpdate = array(
        'id',
        'product_id',
    );
    

    /**
     * @return mixed
     */
    public function create()
    {
        return Client::createResource('/products/' . $this->product_id . '/variants', $this->getCreateFields());
    }

    public function update()
    {
        Client::updateResource('/products/' . $this->product_id . '/variants/' . $this->id, $this->getUpdateFields());
    }
}

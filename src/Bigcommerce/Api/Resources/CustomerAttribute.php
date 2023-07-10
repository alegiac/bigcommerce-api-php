<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class CustomerAttribute extends Resource
{
    /** @var string[] */
    protected $ignoreOnCreate = array(
        'id',
    );

    protected $ignoreOnUpdate = array(
        'id',
    );

    public function create()
    {
        return Client::createResource('/customers/attributes', $this->getCreateFields());
    }

    public function update()
    {

    }

    public function delete()
    {
    }
}

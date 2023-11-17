<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class CustomerGroup extends Resource
{
    /** @var string[] */
    protected $ignoreOnCreate = array(
        'id',
    );

    /** @var string[] */
    protected $ignoreOnUpdate = array(
        'id',
        'date_created',
        'date_modified',
    );

    /**
     * @return mixed
     */
    public function create()
    {
        return Client::createCustomer($this->getCreateFields());
    }

    /**
     * @return mixed
     */
    public function update()
    {
        return Client::updateCustomer($this->id, $this->getUpdateFields());
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        return Client::deleteCustomer($this->id);
    }
}

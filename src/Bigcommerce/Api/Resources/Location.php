<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class Location extends Resource
{
    /** @var string[] */
    protected $ignoreOnCreate = array(
        'id',
    );

    /** @var string[] */
    protected $ignoreOnUpdate = array(
        'id',
    );

    /**
     * @return mixed
     */
    public function create()
    {
        return Client::createLocation($this->getCreateFields());
    }

    /**
     * @return mixed
     */
    public function update()
    {
        return Client::updateLocation($this->id, $this->getUpdateFields());
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        return Client::deleteLocation($this->id);
    }
}

<?php

namespace Bigcommerce\Api\Resources;

use Bigcommerce\Api\Resource;
use Bigcommerce\Api\Client;

class CustomerAttributeValue extends Resource
{
    /** @var string[] */
    protected $ignoreOnCreate = array(
        'id',
    );

    protected $ignoreOnUpdate = array(
        'id',
    );


    public function upsert()
    {

    }

    public function create()
    {
    }

    public function update()
    {

    }

    public function delete()
    {
    }
}

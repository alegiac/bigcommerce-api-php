<?php

namespace Bigcommerce\Api\Resources;

enum ResourceEnum: string
{
    case Resource = "Resource";
    case Product = "Product";
    case Order = "Order";

    public static function data(): array
    {
        return [
            self::Product, self::Order, self::Resource
        ];
    }
}
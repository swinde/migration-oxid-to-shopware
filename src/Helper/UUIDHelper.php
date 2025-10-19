<?php declare(strict_types=1);

namespace MigrationSwinde\MigrationShop\Helper;

class UUIDHelper{
    public static function uuid_create(): string
    {
        return bin2hex(random_bytes(16));
    }
}
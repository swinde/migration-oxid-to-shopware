<?php

namespace MigrationSwinde\MigrationOxidToShopware\Helper;

final class UUIDHelper
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}

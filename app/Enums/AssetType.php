<?php

namespace App\Enums;

enum AssetType: string
{
    case Native = 'NATIVE';
    case Erc20 = 'ERC20';

    public const string DEFAULT_NATIVE_DECIMALS = '18';

    public function isNative(): bool
    {
        return $this === self::Native;
    }
}

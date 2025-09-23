<?php

namespace App\Events\Asset;

use App\Models\AssetTransfer;
use Illuminate\Foundation\Events\Dispatchable;

class AssetTransferUpdated
{
    use Dispatchable;

    public function __construct(
        public AssetTransfer $assetTransfer
    ) {}
}

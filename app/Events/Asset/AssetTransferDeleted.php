<?php

namespace App\Events\Asset;

use App\Models\AssetTransfer;
use Illuminate\Foundation\Events\Dispatchable;

class AssetTransferDeleted
{
    use Dispatchable;

    public function __construct(
        public AssetTransfer $assetTransfer
    ) {}
}

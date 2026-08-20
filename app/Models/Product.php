<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $guarded = [];
}

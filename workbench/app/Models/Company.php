<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $country
 * @property string $type
 * @property string|null $tax_id
 * @property string $name
 * @property string|null $address
 * @property string|null $regon
 * @property string|null $iban
 */
class Company extends Model
{
    protected $guarded = [];
}

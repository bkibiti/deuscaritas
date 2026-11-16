<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The kind of thing a request asks for (maintenance, ICT support, transport...). It also
 * decides whether the Director signs: always for the category, or from a cost threshold.
 */
class RequestCategory extends Model
{
    protected $table = 'request_categories';

    protected $fillable = ['name', 'description', 'requires_director', 'director_cost_threshold', 'is_active'];

    protected function casts(): array
    {
        return [
            'requires_director' => 'boolean',
            'director_cost_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether a request in this category, with this estimated cost, goes to the Director.
     */
    public function needsDirector(?float $estimatedCost): bool
    {
        if ($this->requires_director) {
            return true;
        }

        return $this->director_cost_threshold !== null
            && $estimatedCost !== null
            && $estimatedCost >= (float) $this->director_cost_threshold;
    }
}

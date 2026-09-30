<?php

namespace Database\Factories;

use App\Domain\Gamification\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'slug' => \Illuminate\Support\Str::slug($title),
            'title' => ucfirst($title),
            'description' => fake()->sentence(),
            'icon' => null,
        ];
    }
}

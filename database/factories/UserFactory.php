<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Authority-bearing attributes that are never user-editable through mass
     * assignment. The factory re-applies them via forceFill() so tests can
     * build owners, staff and platform admins without bypassing the model
     * guard. This is test-fixture behavior, never a production write path.
     */
    protected const PRIVILEGED = [
        'tenant_id',
        'role',
        'is_platform_admin',
        'is_active',
        'app_authentication_secret',
    ];

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function create($attributes = [], ?Model $parent = null): Model|Collection
    {
        $privileged = array_intersect_key($attributes, array_flip(self::PRIVILEGED));
        $privileged += ['is_active' => true];
        $plain = array_diff_key($attributes, $privileged);

        $result = parent::create($plain, $parent);

        if ($result instanceof Model) {
            $result->forceFill($privileged)->save();
        } else {
            $result->each(fn (Model $model) => $model->forceFill($privileged)->save());
        }

        return $result;
    }
}

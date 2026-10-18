<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user into the shape shared by /users, /auth/login and /auth/me.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $categories = $this->internalCategories;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => (bool) $this->is_admin,
            'is_active' => (bool) $this->is_active,
            'internal_category_ids' => $categories->pluck('id')->values()->all(),
            'internal_categories' => $categories
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code])
                ->values()
                ->all(),
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'is_system' => $this->is_system,
            'user_count' => $this->user_count,
            'active_permissions_count' => $this->active_permissions_count,
            'total_permissions_count' => $this->total_permissions_count,
            'permissions' => $this->whenLoaded('permissions'),
        ];
    }
}

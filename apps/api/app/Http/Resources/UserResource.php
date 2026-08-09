<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'has_password' => $this->password !== null,
            'email_verified_at' => $this->email_verified_at,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'is_admin' => $this->is_admin,
            'is_super_admin' => $this->is_super_admin,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'settings' => $this->settings,
            'created_at' => $this->created_at,
        ];
    }
}

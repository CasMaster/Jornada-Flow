<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($target)],
            'role' => ['required', Rule::in(['employee', 'manager', 'super_admin'])],
            'team' => ['nullable', 'string', Rule::exists('teams', 'name')],
            'manager_teams' => ['array'],
            'manager_teams.*' => ['integer', Rule::exists('teams', 'id')],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}

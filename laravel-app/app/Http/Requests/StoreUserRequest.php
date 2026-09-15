<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')],
            'role' => ['required', Rule::in(['employee', 'manager', 'super_admin'])],
            'team' => ['nullable', 'string', Rule::exists('teams', 'name')],
            'hired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'manager_teams' => ['array'],
            'manager_teams.*' => ['integer', Rule::exists('teams', 'id')],
            'password' => [Rule::requiredIf(! config('auth.password_recovery_enabled')), 'nullable', 'confirmed', Password::min(8)],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Passwords are deliberately not editable here — staff change their own
     * password under Settings. Authorization is handled by the
     * `module:administration` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->targetUser()->id),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'id')],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Assign at least one role, otherwise the account can reach no modules.',
            'roles.min' => 'Assign at least one role, otherwise the account can reach no modules.',
        ];
    }

    /**
     * Guard against an administrator locking themselves out of Administration.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->targetUser()->is($this->user())) {
                    return;
                }

                $superAdministratorId = Role::where('slug', 'super-administrator')->value('id');

                if ($superAdministratorId === null || in_array($superAdministratorId, $this->submittedRoleIds(), true)) {
                    return;
                }

                $validator->errors()->add(
                    'roles',
                    'You cannot remove your own Super Administrator role.',
                );
            },
        ];
    }

    /**
     * The user being edited.
     */
    protected function targetUser(): User
    {
        return $this->route('user');
    }

    /**
     * The submitted role ids, normalised to integers.
     *
     * @return array<int, int>
     */
    protected function submittedRoleIds(): array
    {
        return array_map(intval(...), array_filter((array) $this->input('roles', []), is_scalar(...)));
    }
}

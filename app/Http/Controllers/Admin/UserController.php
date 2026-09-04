<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly StaffAccountService $accounts) {}

    /**
     * The staff account register.
     */
    public function index(): Response
    {
        $users = User::query()
            ->with('roles')
            // Active staff first, then the deactivated archive.
            ->orderByRaw('deactivated_at is not null')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Resolved in one pass for the page, rather than per row.
        $referencedIds = $this->accounts->referencedUserIds($users->getCollection()->pluck('id'));

        $users->through(fn (User $user) => $this->card($user, $referencedIds));

        return Inertia::render('admin/Users', [
            'users' => $users,
            'roles' => Role::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'grants_all_modules' => $role->grants_all_modules,
                ]),
        ]);
    }

    /**
     * Create a staff account and assign its roles.
     *
     * The account is marked verified on creation: an administrator vouching for
     * the address in person is the verification step in a facility that may not
     * have outbound mail configured.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User;
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
        ]);
        $user->email_verified_at = now();
        $user->save();

        $user->roles()->sync($data['roles']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Account created for {$user->name}."]);

        return back();
    }

    /**
     * Update a staff account's details and role assignment.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
        ])->save();

        $user->roles()->sync($data['roles']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account updated.']);

        return back();
    }

    /**
     * Deactivate a staff account — the account keeps its records but can no
     * longer sign in. This is the normal way to retire a member of staff.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->refuseSelf($request, $user, 'You cannot deactivate your own account.');

        $this->accounts->deactivate($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} deactivated."]);

        return back();
    }

    /**
     * Restore a deactivated staff account.
     */
    public function reactivate(User $user): RedirectResponse
    {
        $this->accounts->reactivate($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} reactivated."]);

        return back();
    }

    /**
     * Permanently delete a staff account.
     *
     * The service refuses this for any account referenced by facility records,
     * so in practice it only removes accounts created in error.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->refuseSelf($request, $user, 'You cannot delete your own account.');

        $name = $user->name;

        $this->accounts->delete($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} deleted."]);

        return back();
    }

    /**
     * Guard against an administrator locking themselves out.
     *
     * @throws ValidationException
     */
    private function refuseSelf(Request $request, User $user, string $message): void
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['delete' => $message]);
        }
    }

    /**
     * @param  Collection<int, int>  $referencedIds  Users referenced by any facility record.
     * @return array<string, mixed>
     */
    private function card(User $user, Collection $referencedIds): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
            'role_ids' => $user->roles->pluck('id')->values(),
            'created_at' => $user->created_at?->toDateString(),
            'deactivated_at' => $user->deactivated_at?->toDateString(),
            'is_active' => ! $user->isDeactivated(),
            // Drives whether the UI offers "Delete" or only "Deactivate".
            'can_be_deleted' => ! $referencedIds->contains($user->id),
        ];
    }
}

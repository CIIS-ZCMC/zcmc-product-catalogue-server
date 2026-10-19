<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administrator-only user management (routes are guarded by the `admin` middleware).
 */
class UserController extends Controller
{
    /**
     * Paginated user list with search and role/category/status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(5, min(100, (int) $request->input('per_page', 15)));

        $query = User::with('internalCategories')->orderBy('first_name')->orderBy('last_name')->orderBy('id');

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw($this->fullNameSql() . ' like ?', [$like]);
            });
        }

        $role = $request->input('role');
        if ($role === 'admin') {
            $query->where('is_admin', true);
        } elseif ($role === 'encoder') {
            $query->where('is_admin', false);
        }

        if ($request->filled('internal_category_id')) {
            $categoryId = (int) $request->input('internal_category_id');
            $query->whereHas('internalCategories', fn ($q) => $q->where('internal_categories.id', $categoryId));
        }

        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->paginate($perPage)
            ->through(fn (User $user) => (new UserResource($user))->resolve());

        return response()->json($users);
    }

    /**
     * Create a user.
     */
    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $plainPassword = !empty($data['password'])
            ? $data['password']
            : $this->generateInitialPassword();

        $user = DB::transaction(function () use ($data, $plainPassword) {
            $user = User::create([
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'email' => $data['email'],
                'password' => $plainPassword,
                'is_admin' => $data['is_admin'],
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => true,
            ]);

            $user->internalCategories()->sync($data['internal_category_ids'] ?? []);

            return $user;
        });

        $user->load('internalCategories');

        ActivityLog::record(
            'CREATED',
            "User account created: {$user->name} ({$user->email})",
            'User',
            $user->id,
            $user->email,
            null,
            $this->auditSnapshot($user)
        );

        SendWelcomeEmailJob::dispatch($user, $plainPassword);

        return response()->json(['data' => (new UserResource($user))->resolve()], 201);
    }

    /**
     * Update a user. Password is only changed when supplied.
     */
    public function update(UserRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($request, $data, $id) {
            // Lock active administrators so concurrent requests cannot both remove the last one.
            User::where('is_admin', true)->where('is_active', true)->lockForUpdate()->pluck('id');

            $user = User::with('internalCategories')->findOrFail($id);
            $before = $this->auditSnapshot($user);

            $isAdmin = array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : (bool) $user->is_admin;
            $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $user->is_active;

            // Only an active administrator can lose admin access, and the caller is always one.
            if ($user->is_admin && $user->is_active && (!$isAdmin || !$isActive)) {
                if ($request->user()->id === $user->id) {
                    return $this->refusal('You cannot demote or deactivate your own account.');
                }
                if ($this->isLastActiveAdmin($user)) {
                    return $this->refusal('The last active administrator cannot be demoted or deactivated.');
                }
            }

            $attributes = [
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'email' => $data['email'],
                'is_admin' => $isAdmin,
                'is_active' => $isActive,
            ];
            if (!empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);

            if (array_key_exists('internal_category_ids', $data)) {
                $user->internalCategories()->sync($data['internal_category_ids'] ?? []);
            }

            // A deactivated account must lose access immediately, not at token expiry.
            if (!$isActive) {
                $user->tokens()->delete();
            }

            return ['user' => $user->load('internalCategories'), 'before' => $before];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        ['user' => $user, 'before' => $before] = $result;
        $after = $this->auditSnapshot($user);

        $changedOld = [];
        $changedNew = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changedOld[$key] = $before[$key] ?? null;
                $changedNew[$key] = $value;
            }
        }
        if (!empty($data['password'])) {
            $changedNew['password'] = '[CHANGED]';
        }

        if (!empty($changedNew)) {
            ActivityLog::record(
                'UPDATED',
                "User account updated: {$user->name} ({$user->email})",
                'User',
                $user->id,
                $user->email,
                $changedOld,
                $changedNew
            );
        }

        return response()->json(['data' => (new UserResource($user))->resolve()]);
    }

    /**
     * Delete a user. Activity log rows keep their text snapshot; user_id is nulled by the FK.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            User::where('is_admin', true)->where('is_active', true)->lockForUpdate()->pluck('id');

            $user = User::with('internalCategories')->findOrFail($id);

            if ($request->user()->id === $user->id) {
                return $this->refusal('You cannot delete your own account.');
            }
            if ($user->is_admin && $user->is_active && $this->isLastActiveAdmin($user)) {
                return $this->refusal('The last active administrator cannot be deleted.');
            }

            $snapshot = $this->auditSnapshot($user);

            ActivityLog::record(
                'DELETED',
                "User account deleted: {$user->name} ({$user->email})",
                'User',
                $user->id,
                $user->email,
                $snapshot,
                null
            );

            $user->tokens()->delete();
            $user->delete();

            return response()->json(['message' => 'User deleted.']);
        });
    }

    private function fullNameSql(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "(first_name || ' ' || last_name)"
            : "CONCAT(first_name, ' ', last_name)";
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return !User::where('is_admin', true)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }

    private function refusal(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'must_change_password' => (bool) $user->must_change_password,
            'internal_category_ids' => $user->internalCategories->pluck('id')->sort()->values()->all(),
        ];
    }

    /**
     * Generate an initial password compliant with security rules (at least 12 chars, letters, digits, symbols).
     */
    private function generateInitialPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '@#$%&*!';
        $all = $upper . $lower . $digits . $symbols;

        $pwd = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = 0; $i < 8; $i++) {
            $pwd[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($pwd);

        return implode('', $pwd);
    }
}

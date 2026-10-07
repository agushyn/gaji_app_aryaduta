<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of internal users.
     */
    public function index(Request $request): Response
    {
        $query = User::with('roles:id,name,label');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->string('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->string('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $users = $query->latest()->paginate(10)->withQueryString();
        $roles = Role::select('id', 'name', 'label')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    /**
     * Store a newly created internal user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->roles()->sync([$validated['role_id']]);

        $this->auditLogService->log(
            action: 'user.created',
            subject: $user,
            changes: [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role_id' => $validated['role_id'],
            ]
        );

        return redirect()->route('users.index')->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $oldStatus = $user->is_active;
        $user->is_active = ! $oldStatus;
        $user->save();

        $this->auditLogService->log(
            action: 'user.status_toggled',
            subject: $user,
            changes: [
                'previous_status' => $oldStatus,
                'new_status' => $user->is_active,
            ]
        );

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('users.index')->with('success', "Status akun {$user->name} berhasil {$statusText}.");
    }
}

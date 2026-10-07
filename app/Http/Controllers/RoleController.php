<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of roles and their permissions.
     */
    public function index(): Response
    {
        $roles = Role::with(['permissions:id,name,label,group', 'users:id,name,email'])
            ->get();

        $allPermissions = Permission::all()->groupBy('group');

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'groupedPermissions' => $allPermissions,
        ]);
    }
}

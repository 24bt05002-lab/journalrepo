<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::check() && method_exists(Auth::user(), 'isAdmin') && !Auth::user()->isAdmin()) {
            abort(403, 'Akses terhad kepada Pentadbir (Admin) sahaja.');
        }

        $hasArticlesRelation = method_exists(User::class, 'articles') && Schema::hasTable('articles');

        $query = User::query();
        if ($hasArticlesRelation) {
            $query->withCount('articles');
        }

        // 1. Carian Nama atau E-mel
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 2. Penapis Peranan (Role)
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // 3. Susunan
        $sort = $request->input('sort', 'latest');
        if ($sort === 'name') {
            $query->orderBy('name');
        } elseif ($sort === 'oldest') {
            $query->oldest();
        } elseif ($sort === 'articles' && $hasArticlesRelation) {
            $query->orderByDesc('articles_count')->orderBy('name');
        } else {
            $query->latest();
        }

        $users = $query->paginate(15)->withQueryString();

        $dbRoles = Schema::hasColumn('users', 'role')
            ? User::whereNotNull('role')->where('role', '!=', '')->distinct()->pluck('role')->toArray()
            : [];

        $availableRoles = array_values(array_unique(array_merge(
            ['admin', 'lecturer', 'researcher', 'user'],
            $dbRoles
        )));

        $totalUsers = User::count();
        $roleCounts = Schema::hasColumn('users', 'role')
            ? User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role')->toArray()
            : [];

        $viewName = view()->exists('admin.users')
            ? 'admin.users'
            : (view()->exists('admin.users.index') ? 'admin.users.index' : 'admin.users');

        return view($viewName, compact(
            'users',
            'availableRoles',
            'totalUsers',
            'roleCounts',
            'hasArticlesRelation'
        ));
    }

    public function updateRole(Request $request, User $user)
    {
        if (Auth::check() && method_exists(Auth::user(), 'isAdmin') && !Auth::user()->isAdmin()) {
            abort(403, 'Akses terhad kepada Pentadbir (Admin) sahaja.');
        }

        $validated = $request->validate([
            'role' => 'required|string|max:50',
        ]);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak boleh menukar peranan akaun anda sendiri.');
        }

        $user->role = $validated['role'];
        $user->save();

        return back()->with('success', "Peranan bagi {$user->name} berjaya dikemas kini kepada " . strtoupper($validated['role']) . '.');
    }
}
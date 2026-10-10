<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account', ['admin' => $request->user('admin')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($admin->id)],
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['nullable', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        $admin->name = $data['name'];
        $admin->email = strtolower($data['email']);

        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }

        $admin->save();

        return back()->with('status', 'Account updated.');
    }
}

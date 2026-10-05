<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Modules\Scheduling\Services\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        Validator::make($input, ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)]])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
            app(WorkspaceService::class)->create($user);

            return $user;
        });
    }
}

<?php

namespace ITHilbert\UserAuth\App\Console\Commands;

use Illuminate\Console\Command;
use ITHilbert\UserAuth\Entities\Role;

class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userauth:role:assign {role : The name, ID, or display name of the role} {email : The email address of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assigns a specific role to a user by their email address';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $roleIdentifier = $this->argument('role');
        $email = $this->argument('email');

        // Dynamically resolving the User Model based on standard Laravel structure
        $userModelClass = config('auth.providers.users.model', '\\App\\Models\\User');
        
        if (!class_exists($userModelClass)) {
            $this->error("User model [{$userModelClass}] not found.");
            return 1;
        }

        $user = $userModelClass::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email [{$email}] not found.");
            return 1;
        }

        $role = Role::where('id', $roleIdentifier)
                    ->orWhere('role', $roleIdentifier)
                    ->orWhere('role_display', $roleIdentifier)
                    ->first();

        if (!$role) {
            $this->error("Role [{$roleIdentifier}] not found in database.");
            return 1;
        }

        $user->role_id = $role->id;
        $user->save();

        $this->info("Successfully assigned role '{$role->role}' to user {$user->email}.");

        return 0;
    }
}

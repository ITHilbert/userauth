<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\App\Console\Commands;

use Illuminate\Console\Command;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;

final class AssignRoleCommand extends Command
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
    public function handle(UserModel $userModel)
    {
        $roleIdentifier = $this->argument('role');
        $email = $this->argument('email');

        $user = $userModel->query()->where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return 1;
        }

        $role = Role::where('id', $roleIdentifier)
            ->orWhere('role', $roleIdentifier)
            ->orWhere('role_display', $roleIdentifier)
            ->first();

        if (! $role) {
            $this->error("Role [{$roleIdentifier}] not found in database.");

            return 1;
        }

        $user->role_id = $role->id;
        $user->save();

        $this->info("Successfully assigned role '{$role->role}' to user {$user->email}.");

        return 0;
    }
}

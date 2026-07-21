
<?php

namespace App\Middleware;

use App\Core\Session;

class RoleMiddleware implements Middleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(): void
    {
        $userRole = Session::get('user_role');

        if (!$userRole || !in_array($userRole, $this->allowedRoles)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}


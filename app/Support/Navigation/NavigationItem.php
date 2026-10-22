<?php

namespace App\Support\Navigation;

use App\Enums\Role;

/**
 * One entry of the side menu. A group has children and no page of its own.
 */
final readonly class NavigationItem
{
    /**
     * @param  list<Role>  $roles  roles that can see the entry
     * @param  list<NavigationItem>  $children
     */
    public function __construct(
        public string $key,
        public string $icon,
        public array $roles,
        public array $children = [],
    ) {}

    public function isVisibleTo(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isGroup(): bool
    {
        return $this->children !== [];
    }
}

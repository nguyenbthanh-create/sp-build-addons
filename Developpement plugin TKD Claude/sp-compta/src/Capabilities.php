<?php

declare(strict_types=1);

namespace SpCompta;

final class Capabilities
{
    public const MANAGE_COMPTA = 'sp_compta_manager';

    private const BUREAU_OPTION = 'sp_compta_bureau_users';

    public function register(): void
    {
        $administrator = get_role('administrator');

        if ($administrator !== null && !$administrator->has_cap(self::MANAGE_COMPTA)) {
            $administrator->add_cap(self::MANAGE_COMPTA);
        }
    }

    public function currentUserCanManage(): bool
    {
        return current_user_can(self::MANAGE_COMPTA);
    }

    /**
     * @return int[]
     */
    public function bureauUsers(): array
    {
        $ids = get_option(self::BUREAU_OPTION, []);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * Accorde la capacite directement aux utilisateurs listes (quel que
     * soit leur role) et la retire aux utilisateurs precedemment listes
     * qui ne le sont plus. N'affecte jamais l'octroi via le role
     * administrateur fait par register() - un administrateur garde son
     * acces meme s'il n'est pas dans cette liste.
     *
     * @param int[] $userIds
     */
    public function syncBureauUsers(array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $previous = $this->bureauUsers();

        foreach (array_diff($previous, $userIds) as $removedId) {
            $user = get_user_by('id', $removedId);

            if ($user !== false) {
                $user->remove_cap(self::MANAGE_COMPTA);
            }
        }

        foreach ($userIds as $addedId) {
            $user = get_user_by('id', $addedId);

            if ($user !== false) {
                $user->add_cap(self::MANAGE_COMPTA);
            }
        }

        update_option(self::BUREAU_OPTION, $userIds);
    }
}

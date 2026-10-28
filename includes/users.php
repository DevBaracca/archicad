<?php
/**
 * Baracca - Site archicad.baracca.fr
 * Comptes clients du configurateur (email + mot de passe, hash bcrypt),
 * créés en ligne depuis inscription.php et gérés depuis le back-office
 * (data/users.json).
 */

require_once __DIR__ . '/../config.php';

function archicad_users_file(): string
{
    return ARCHICAD_DATA_DIR . '/users.json';
}

function archicad_load_users(): array
{
    $file = archicad_users_file();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['users']) || !is_array($data['users'])) {
        return [];
    }
    return $data['users'];
}

function archicad_save_users(array $users): bool
{
    if (!is_dir(ARCHICAD_DATA_DIR)) {
        mkdir(ARCHICAD_DATA_DIR, 0775, true);
    }
    return file_put_contents(archicad_users_file(), json_encode(['users' => array_values($users)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function archicad_users_count(): int
{
    return count(archicad_load_users());
}

function archicad_find_user_by_email(string $email): ?array
{
    foreach (archicad_load_users() as $user) {
        if (hash_equals(strtolower((string) ($user['email'] ?? '')), strtolower($email))) {
            return $user;
        }
    }
    return null;
}

function archicad_find_user_by_id(string $id): ?array
{
    foreach (archicad_load_users() as $user) {
        if (($user['id'] ?? '') === $id) {
            return $user;
        }
    }
    return null;
}

/**
 * Crée un compte client. $fields : email, nom, prenom, telephone, adresse_numero,
 * adresse_rue, code_postal, ville, reference_cadastrale (facultative).
 * Retourne le compte créé, ou false si l'email est invalide ou déjà utilisé.
 */
function archicad_add_user(array $fields, string $password): array|false
{
    $email = strtolower(trim((string) ($fields['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || archicad_find_user_by_email($email) !== null) {
        return false;
    }
    $users = archicad_load_users();
    $user = [
        'id' => bin2hex(random_bytes(8)),
        'email' => $email,
        'nom' => trim((string) ($fields['nom'] ?? '')),
        'prenom' => trim((string) ($fields['prenom'] ?? '')),
        'telephone' => trim((string) ($fields['telephone'] ?? '')),
        'adresse_numero' => trim((string) ($fields['adresse_numero'] ?? '')),
        'adresse_rue' => trim((string) ($fields['adresse_rue'] ?? '')),
        'code_postal' => trim((string) ($fields['code_postal'] ?? '')),
        'ville' => trim((string) ($fields['ville'] ?? '')),
        'reference_cadastrale' => trim((string) ($fields['reference_cadastrale'] ?? '')),
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'created' => date('Y-m-d'),
    ];
    $users[] = $user;
    return archicad_save_users($users) ? $user : false;
}

function archicad_delete_user(string $id): bool
{
    $users = archicad_load_users();
    foreach ($users as $i => $user) {
        if (($user['id'] ?? '') === $id) {
            unset($users[$i]);
            return archicad_save_users($users);
        }
    }
    return false;
}

function archicad_set_user_password(string $id, string $password): bool
{
    $users = archicad_load_users();
    foreach ($users as $i => $user) {
        if (($user['id'] ?? '') === $id) {
            $users[$i]['hash'] = password_hash($password, PASSWORD_DEFAULT);
            return archicad_save_users($users);
        }
    }
    return false;
}

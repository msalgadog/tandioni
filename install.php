<?php

declare(strict_types=1);

$lockFile = __DIR__ . '/install.lock';
$envFile = __DIR__ . '/.env';

$errors = [];
$success = false;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function parseEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $data = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $data[trim($key)] = trim($value);
    }

    return $data;
}

function envLine(string $key, string $value): string
{
    $escaped = str_replace('"', '\"', $value);
    return sprintf('%s="%s"', $key, $escaped);
}

function schemaStatements(string $driver): array
{
    $isSqlite = $driver === 'sqlite';
    $isMysql = $driver === 'mysql';

    $idType = match ($driver) {
        'pgsql' => 'BIGSERIAL PRIMARY KEY',
        'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        default => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
    };

    $fkType = match ($driver) {
        'sqlite' => 'INTEGER',
        default => 'BIGINT',
    };

    $timestamp = match ($driver) {
        'sqlite' => 'DATETIME',
        default => 'TIMESTAMP',
    };

    $decimal = $driver === 'pgsql' ? 'NUMERIC(12,2)' : 'DECIMAL(12,2)';
    $boolean = $driver === 'pgsql' ? 'BOOLEAN' : ($driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)');

    $statements = [];

    $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id {$idType},
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    second_last_name VARCHAR(255) NULL,
    phone VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    profile_photo_path VARCHAR(255) NULL,
    postal_code VARCHAR(10) NOT NULL,
    state VARCHAR(255) NOT NULL,
    municipality VARCHAR(255) NOT NULL,
    colony VARCHAR(255) NOT NULL,
    street VARCHAR(255) NOT NULL,
    external_number VARCHAR(255) NOT NULL,
    internal_number VARCHAR(255) NULL,
    phone_home VARCHAR(255) NULL,
    phone_office VARCHAR(255) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    email_verified_at {$timestamp} NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at {$timestamp} NULL,
    updated_at {$timestamp} NULL
)
SQL;

    $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS tandas (
    id {$idType},
    name VARCHAR(255) NOT NULL,
    amount {$decimal} NOT NULL,
    frequency VARCHAR(20) NOT NULL CHECK (frequency IN ('weekly', 'biweekly', 'monthly')),
    participants_count INTEGER NOT NULL,
    start_date DATE NOT NULL,
    delivery_date DATE NOT NULL,
    payment_mode VARCHAR(20) NOT NULL CHECK (payment_mode IN ('intermediary', 'direct')),
    created_at {$timestamp} NULL,
    updated_at {$timestamp} NULL
)
SQL;

    $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS tanda_participants (
    id {$idType},
    tanda_id {$fkType} NOT NULL,
    user_id {$fkType} NOT NULL,
    position INTEGER NOT NULL,
    is_winner {$boolean} NOT NULL DEFAULT {$isMysql ? '0' : 'FALSE'},
    created_at {$timestamp} NULL,
    updated_at {$timestamp} NULL,
    UNIQUE (tanda_id, position),
    UNIQUE (tanda_id, user_id),
    FOREIGN KEY (tanda_id) REFERENCES tandas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL;

    $statements[] = <<<SQL
CREATE TABLE IF NOT EXISTS payments (
    id {$idType},
    tanda_id {$fkType} NOT NULL,
    participant_id {$fkType} NOT NULL,
    recipient_user_id {$fkType} NULL,
    due_date DATE NOT NULL,
    amount_snapshot {$decimal} NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'uploaded', 'validated', 'rejected')),
    receipt_path VARCHAR(255) NULL,
    validated_at {$timestamp} NULL,
    rejected_at {$timestamp} NULL,
    created_at {$timestamp} NULL,
    updated_at {$timestamp} NULL,
    FOREIGN KEY (tanda_id) REFERENCES tandas(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES tanda_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
)
SQL;

    $statements[] = 'CREATE INDEX IF NOT EXISTS payments_status_due_date_index ON payments (status, due_date)';

    if ($isSqlite) {
        $statements[] = 'PRAGMA foreign_keys = ON';
    }

    return $statements;
}

$envData = parseEnvFile($envFile);

$defaults = [
    'db_driver' => $envData['DB_CONNECTION'] ?? 'mysql',
    'db_host' => $envData['DB_HOST'] ?? '127.0.0.1',
    'db_port' => $envData['DB_PORT'] ?? '3306',
    'db_name' => $envData['DB_DATABASE'] ?? '',
    'db_user' => $envData['DB_USERNAME'] ?? '',
    'db_pass' => $envData['DB_PASSWORD'] ?? '',
    'sqlite_path' => $envData['DB_DATABASE'] ?? (__DIR__ . '/database.sqlite'),
    'admin_first_name' => '',
    'admin_last_name' => '',
    'admin_second_last_name' => '',
    'admin_phone' => '',
    'admin_email' => '',
    'admin_postal_code' => '',
    'admin_state' => '',
    'admin_municipality' => '',
    'admin_colony' => '',
    'admin_street' => '',
    'admin_external_number' => '',
    'admin_internal_number' => '',
    'admin_phone_home' => '',
    'admin_phone_office' => '',
];

$values = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        if (isset($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }

    $driver = $values['db_driver'];
    $requiredDb = ['db_driver'];
    if ($driver === 'sqlite') {
        $requiredDb[] = 'sqlite_path';
    } else {
        $requiredDb = array_merge($requiredDb, ['db_host', 'db_port', 'db_name', 'db_user']);
    }

    $requiredAdmin = [
        'admin_first_name',
        'admin_last_name',
        'admin_phone',
        'admin_email',
        'admin_postal_code',
        'admin_state',
        'admin_municipality',
        'admin_colony',
        'admin_street',
        'admin_external_number',
    ];

    foreach ($requiredDb as $field) {
        if ($values[$field] === '') {
            $errors[] = "El campo {$field} es obligatorio.";
        }
    }

    foreach ($requiredAdmin as $field) {
        if ($values[$field] === '') {
            $errors[] = "El campo {$field} es obligatorio.";
        }
    }

    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo del administrador no es válido.';
    }

    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    if ($adminPassword === '') {
        $errors[] = 'La contraseña del administrador es obligatoria.';
    }

    if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
        $errors[] = 'El motor de base de datos no es válido.';
    }

    if (!file_exists($lockFile) && $errors === []) {
        $dsn = '';
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $values['sqlite_path'];
        } elseif ($driver === 'pgsql') {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $values['db_host'], $values['db_port'], $values['db_name']);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $values['db_host'],
                $values['db_port'],
                $values['db_name']
            );
        }

        try {
            $pdo = new PDO($dsn, $values['db_user'] ?? null, $values['db_pass'] ?? null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->beginTransaction();

            foreach (schemaStatements($driver) as $statement) {
                $pdo->exec($statement);
            }

            $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $nowExpression = match ($driver) {
                'sqlite' => "datetime('now')",
                'pgsql' => 'CURRENT_TIMESTAMP',
                default => 'NOW()',
            };

            $insertSql = <<<SQL
INSERT INTO users (
    first_name,
    last_name,
    second_last_name,
    phone,
    email,
    profile_photo_path,
    postal_code,
    state,
    municipality,
    colony,
    street,
    external_number,
    internal_number,
    phone_home,
    phone_office,
    role,
    email_verified_at,
    password,
    remember_token,
    created_at,
    updated_at
) VALUES (
    :first_name,
    :last_name,
    :second_last_name,
    :phone,
    :email,
    NULL,
    :postal_code,
    :state,
    :municipality,
    :colony,
    :street,
    :external_number,
    :internal_number,
    :phone_home,
    :phone_office,
    'admin',
    {$nowExpression},
    :password,
    NULL,
    {$nowExpression},
    {$nowExpression}
)
SQL;

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                'first_name' => $values['admin_first_name'],
                'last_name' => $values['admin_last_name'],
                'second_last_name' => $values['admin_second_last_name'] ?: null,
                'phone' => $values['admin_phone'],
                'email' => $values['admin_email'],
                'postal_code' => $values['admin_postal_code'],
                'state' => $values['admin_state'],
                'municipality' => $values['admin_municipality'],
                'colony' => $values['admin_colony'],
                'street' => $values['admin_street'],
                'external_number' => $values['admin_external_number'],
                'internal_number' => $values['admin_internal_number'] ?: null,
                'phone_home' => $values['admin_phone_home'] ?: null,
                'phone_office' => $values['admin_phone_office'] ?: null,
                'password' => $passwordHash,
            ]);

            $pdo->commit();

            $envLines = [
                envLine('APP_INSTALLED', 'true'),
                envLine('DB_CONNECTION', $driver),
            ];
            if ($driver === 'sqlite') {
                $envLines[] = envLine('DB_DATABASE', $values['sqlite_path']);
            } else {
                $envLines[] = envLine('DB_HOST', $values['db_host']);
                $envLines[] = envLine('DB_PORT', $values['db_port']);
                $envLines[] = envLine('DB_DATABASE', $values['db_name']);
                $envLines[] = envLine('DB_USERNAME', $values['db_user']);
                $envLines[] = envLine('DB_PASSWORD', $values['db_pass']);
            }

            file_put_contents($envFile, implode(PHP_EOL, $envLines) . PHP_EOL);
            file_put_contents($lockFile, 'installed');

            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'No se pudo completar la instalación: ' . $exception->getMessage();
        }
    } elseif (file_exists($lockFile)) {
        $errors[] = 'La aplicación ya está instalada. Si quieres reinstalar, ejecuta uninstall.php primero.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Instalador de TandiOni</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f6f6; padding: 2rem; }
        .container { background: #fff; border-radius: 8px; padding: 2rem; max-width: 820px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        label { display: block; font-weight: bold; margin-bottom: 0.35rem; }
        input, select { width: 100%; padding: 0.5rem; border-radius: 4px; border: 1px solid #ccc; }
        .section { margin-top: 1.5rem; }
        .actions { margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center; }
        .alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-error { background: #ffe6e6; color: #842029; }
        .alert-success { background: #e6ffed; color: #0f5132; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalador de TandiOni</h1>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Instalación completada correctamente. Ya puedes ingresar con el usuario administrador.
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (file_exists($lockFile) && !$success): ?>
            <div class="alert alert-error">
                La aplicación ya está instalada. Si quieres reinstalar, ejecuta <a href="uninstall.php">uninstall.php</a>.
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="section">
                <h2>Base de datos</h2>
                <div class="grid">
                    <div>
                        <label for="db_driver">Motor</label>
                        <select name="db_driver" id="db_driver">
                            <option value="mysql" <?= $values['db_driver'] === 'mysql' ? 'selected' : '' ?>>MySQL</option>
                            <option value="pgsql" <?= $values['db_driver'] === 'pgsql' ? 'selected' : '' ?>>PostgreSQL</option>
                            <option value="sqlite" <?= $values['db_driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite</option>
                        </select>
                    </div>
                    <div>
                        <label for="db_host">Servidor</label>
                        <input type="text" name="db_host" id="db_host" value="<?= h($values['db_host']) ?>">
                    </div>
                    <div>
                        <label for="db_port">Puerto</label>
                        <input type="text" name="db_port" id="db_port" value="<?= h($values['db_port']) ?>">
                    </div>
                    <div>
                        <label for="db_name">Base de datos</label>
                        <input type="text" name="db_name" id="db_name" value="<?= h($values['db_name']) ?>">
                    </div>
                    <div>
                        <label for="db_user">Usuario</label>
                        <input type="text" name="db_user" id="db_user" value="<?= h($values['db_user']) ?>">
                    </div>
                    <div>
                        <label for="db_pass">Contraseña</label>
                        <input type="password" name="db_pass" id="db_pass" value="<?= h($values['db_pass']) ?>">
                    </div>
                    <div>
                        <label for="sqlite_path">Ruta SQLite</label>
                        <input type="text" name="sqlite_path" id="sqlite_path" value="<?= h($values['sqlite_path']) ?>">
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Administrador</h2>
                <div class="grid">
                    <div>
                        <label for="admin_first_name">Nombre</label>
                        <input type="text" name="admin_first_name" id="admin_first_name" value="<?= h($values['admin_first_name']) ?>">
                    </div>
                    <div>
                        <label for="admin_last_name">Apellido paterno</label>
                        <input type="text" name="admin_last_name" id="admin_last_name" value="<?= h($values['admin_last_name']) ?>">
                    </div>
                    <div>
                        <label for="admin_second_last_name">Apellido materno</label>
                        <input type="text" name="admin_second_last_name" id="admin_second_last_name" value="<?= h($values['admin_second_last_name']) ?>">
                    </div>
                    <div>
                        <label for="admin_phone">Teléfono</label>
                        <input type="text" name="admin_phone" id="admin_phone" value="<?= h($values['admin_phone']) ?>">
                    </div>
                    <div>
                        <label for="admin_email">Correo</label>
                        <input type="email" name="admin_email" id="admin_email" value="<?= h($values['admin_email']) ?>">
                    </div>
                    <div>
                        <label for="admin_password">Contraseña</label>
                        <input type="password" name="admin_password" id="admin_password">
                    </div>
                    <div>
                        <label for="admin_postal_code">Código postal</label>
                        <input type="text" name="admin_postal_code" id="admin_postal_code" value="<?= h($values['admin_postal_code']) ?>">
                    </div>
                    <div>
                        <label for="admin_state">Estado</label>
                        <input type="text" name="admin_state" id="admin_state" value="<?= h($values['admin_state']) ?>">
                    </div>
                    <div>
                        <label for="admin_municipality">Municipio</label>
                        <input type="text" name="admin_municipality" id="admin_municipality" value="<?= h($values['admin_municipality']) ?>">
                    </div>
                    <div>
                        <label for="admin_colony">Colonia</label>
                        <input type="text" name="admin_colony" id="admin_colony" value="<?= h($values['admin_colony']) ?>">
                    </div>
                    <div>
                        <label for="admin_street">Calle</label>
                        <input type="text" name="admin_street" id="admin_street" value="<?= h($values['admin_street']) ?>">
                    </div>
                    <div>
                        <label for="admin_external_number">Número exterior</label>
                        <input type="text" name="admin_external_number" id="admin_external_number" value="<?= h($values['admin_external_number']) ?>">
                    </div>
                    <div>
                        <label for="admin_internal_number">Número interior</label>
                        <input type="text" name="admin_internal_number" id="admin_internal_number" value="<?= h($values['admin_internal_number']) ?>">
                    </div>
                    <div>
                        <label for="admin_phone_home">Teléfono casa</label>
                        <input type="text" name="admin_phone_home" id="admin_phone_home" value="<?= h($values['admin_phone_home']) ?>">
                    </div>
                    <div>
                        <label for="admin_phone_office">Teléfono oficina</label>
                        <input type="text" name="admin_phone_office" id="admin_phone_office" value="<?= h($values['admin_phone_office']) ?>">
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Instalar aplicación</button>
                <a href="uninstall.php">Desinstalar</a>
            </div>
        </form>
    </div>
</body>
</html>

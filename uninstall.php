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
        $data[trim($key)] = trim($value, "\" \t\n\r\0\x0B");
    }

    return $data;
}

$envData = parseEnvFile($envFile);

$values = [
    'db_driver' => $envData['DB_CONNECTION'] ?? 'mysql',
    'db_host' => $envData['DB_HOST'] ?? '127.0.0.1',
    'db_port' => $envData['DB_PORT'] ?? '3306',
    'db_name' => $envData['DB_DATABASE'] ?? '',
    'db_user' => $envData['DB_USERNAME'] ?? '',
    'db_pass' => $envData['DB_PASSWORD'] ?? '',
    'sqlite_path' => $envData['DB_DATABASE'] ?? (__DIR__ . '/database.sqlite'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        if (isset($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }

    $driver = $values['db_driver'];
    if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
        $errors[] = 'El motor de base de datos no es válido.';
    }

    $confirmation = trim((string) ($_POST['confirm_delete'] ?? ''));
    if ($confirmation !== 'ELIMINAR') {
        $errors[] = 'Escribe ELIMINAR para confirmar la desinstalación.';
    }

    if ($driver === 'sqlite' && $values['sqlite_path'] === '') {
        $errors[] = 'La ruta SQLite es obligatoria.';
    }

    if ($driver !== 'sqlite' && ($values['db_host'] === '' || $values['db_port'] === '' || $values['db_name'] === '' || $values['db_user'] === '')) {
        $errors[] = 'Completa la información de la base de datos.';
    }

    if ($errors === []) {
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
            $pdo->exec('DROP TABLE IF EXISTS payments');
            $pdo->exec('DROP TABLE IF EXISTS tanda_participants');
            $pdo->exec('DROP TABLE IF EXISTS tandas');
            $pdo->exec('DROP TABLE IF EXISTS users');
            $pdo->commit();

            if (is_file($lockFile)) {
                unlink($lockFile);
            }
            if (is_file($envFile)) {
                unlink($envFile);
            }

            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'No se pudo completar la desinstalación: ' . $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Desinstalador de TandiOni</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f6f6; padding: 2rem; }
        .container { background: #fff; border-radius: 8px; padding: 2rem; max-width: 700px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        label { display: block; font-weight: bold; margin-bottom: 0.35rem; }
        input, select { width: 100%; padding: 0.5rem; border-radius: 4px; border: 1px solid #ccc; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .section { margin-top: 1.5rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-error { background: #ffe6e6; color: #842029; }
        .alert-success { background: #e6ffed; color: #0f5132; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Desinstalador de TandiOni</h1>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Desinstalación completada. Se eliminaron las tablas y la configuración local.
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

        <?php if (!file_exists($lockFile)): ?>
            <div class="alert alert-error">
                No se encontró una instalación activa. Si necesitas instalar, ve a <a href="install.php">install.php</a>.
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
                <label for="confirm_delete">Escribe ELIMINAR para confirmar</label>
                <input type="text" name="confirm_delete" id="confirm_delete">
            </div>

            <div class="section">
                <button type="submit">Desinstalar aplicación</button>
                <a href="install.php">Volver al instalador</a>
            </div>
        </form>
    </div>
</body>
</html>

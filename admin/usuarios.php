<?php
require_once __DIR__ . '/config/init.php';
requiere_login();
requiere_admin();   // 'autor' no gestiona cuentas
$yo = usuario_actual();
$soySuper = es_superadmin();

// SOLO el superadministrador puede asignar o cambiar roles. El admin gestiona
// usuarios (alta, datos, contraseña, baja) pero NO toca el rol.
const ROLES_TODOS = ['superadmin', 'admin', 'autor'];
$ROLES = $soySuper ? ROLES_TODOS : [];   // el admin no ve selector de rol

/** Rol actual (en BD) del usuario con ese id, o '' si no existe. */
function rol_de(int $id): string
{
    $s = db()->prepare('SELECT rol FROM usuarios WHERE id = ?');
    $s->execute([$id]);
    return (string) ($s->fetchColumn() ?: '');
}
/** Cuántos superadministradores quedan. */
function cuenta_superadmin(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'superadmin'")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('usuarios.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id     = (int) ($_POST['id'] ?? 0);
        $nom    = trim($_POST['nombres'] ?? '');
        $apPat  = trim($_POST['ap_paterno'] ?? '');
        $apMat  = trim($_POST['ap_materno'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $rolPed = $_POST['rol'] ?? '';
        $pass   = (string) ($_POST['password'] ?? '');

        // --- Reglas de rol ---
        $rolPrevio = $id > 0 ? rol_de($id) : '';
        // 1) Nadie que no sea superadmin puede tocar a un superadmin.
        if ($rolPrevio === 'superadmin' && !$soySuper) {
            flash_set('Solo el superadministrador puede modificar a otro superadministrador.', 'warning'); redir('usuarios.php');
        }
        // 2) El rol SOLO lo decide el superadmin. Para el admin se mantiene
        //    el rol que ya tenía (al editar) o 'autor' (al crear); se ignora lo que envíe.
        if ($soySuper) {
            $rol = in_array($rolPed, ROLES_TODOS, true) ? $rolPed : ($rolPrevio ?: 'autor');
        } else {
            $rol = $rolPrevio ?: 'autor';
        }
        // 3) No dejar al sistema sin ningún superadministrador.
        if ($rolPrevio === 'superadmin' && $rol !== 'superadmin' && cuenta_superadmin() <= 1) {
            flash_set('Debe quedar al menos un superadministrador.', 'warning'); redir('usuarios.php');
        }

        if ($nom === '' || $apPat === '' || $email === '') {
            flash_set('Nombres, apellido paterno y correo son obligatorios.', 'warning'); redir('usuarios.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('El correo no es válido.', 'warning'); redir('usuarios.php');
        }
        $chk = db()->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) { flash_set('Ya existe otro usuario con ese correo.', 'warning'); redir('usuarios.php'); }

        if ($id > 0) {
            if ($pass !== '') {
                if (strlen($pass) < 6) { flash_set('La contraseña debe tener al menos 6 caracteres.', 'warning'); redir('usuarios.php'); }
                db()->prepare('UPDATE usuarios SET nombres=?, ap_paterno=?, ap_materno=?, email=?, rol=?, password_hash=? WHERE id=?')
                   ->execute([$nom, $apPat, ($apMat ?: null), $email, $rol, password_hash($pass, PASSWORD_DEFAULT), $id]);
            } else {
                db()->prepare('UPDATE usuarios SET nombres=?, ap_paterno=?, ap_materno=?, email=?, rol=? WHERE id=?')
                   ->execute([$nom, $apPat, ($apMat ?: null), $email, $rol, $id]);
            }
            // si me edité a mí mismo, refrescar la sesión
            if ($id === (int) $yo['id']) {
                $_SESSION['usuario_nombre'] = trim($nom . ' ' . $apPat);
                $_SESSION['usuario_email']  = $email;
                $_SESSION['usuario_rol']    = $rol;
            }
            flash_set('Usuario actualizado.');
        } else {
            if (strlen($pass) < 6) { flash_set('La contraseña debe tener al menos 6 caracteres.', 'warning'); redir('usuarios.php'); }
            db()->prepare('INSERT INTO usuarios (nombres, ap_paterno, ap_materno, email, password_hash, rol, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())')
               ->execute([$nom, $apPat, ($apMat ?: null), $email, password_hash($pass, PASSWORD_DEFAULT), $rol]);
            flash_set('Usuario creado.');
        }
        redir('usuarios.php');
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $yo['id']) { flash_set('No puedes eliminar tu propio usuario.', 'warning'); redir('usuarios.php'); }
        $rolObjetivo = rol_de($id);
        if ($rolObjetivo === 'superadmin' && !$soySuper) {
            flash_set('Solo el superadministrador puede eliminar a otro superadministrador.', 'warning'); redir('usuarios.php');
        }
        if ($rolObjetivo === 'superadmin' && cuenta_superadmin() <= 1) {
            flash_set('No puedes eliminar al último superadministrador.', 'warning'); redir('usuarios.php');
        }
        if ((int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() <= 1) {
            flash_set('Debe quedar al menos un usuario.', 'warning'); redir('usuarios.php');
        }
        db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        flash_set('Usuario eliminado.');
        redir('usuarios.php');
    }
    redir('usuarios.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
    // Un no-superadmin no puede abrir la ficha de un superadmin.
    if ($editar && $editar['rol'] === 'superadmin' && !$soySuper) {
        flash_set('Solo el superadministrador puede editar a otro superadministrador.', 'warning');
        redir('usuarios.php');
    }
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
$filas = db()->query('SELECT id, nombres, ap_paterno, ap_materno, email, rol, created_at
                      FROM usuarios ORDER BY id')->fetchAll();

$titulo = 'Usuarios';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Usuarios</h1>
        <?php if ($modo === 'lista'): ?>
            <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nuevo usuario</a>
        <?php else: ?>
            <a class="btn btn-default" href="usuarios.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
        <?php endif; ?>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <?php if ($modo === 'lista'): ?>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle">
                    <thead><tr><th>#</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($filas as $r):
                        $nom = trim($r['nombres'] . ' ' . $r['ap_paterno'] . ' ' . ($r['ap_materno'] ?? ''));
                        $esSuperFila = $r['rol'] === 'superadmin';
                        $puedeTocar  = !$esSuperFila || $soySuper;   // a un superadmin solo lo toca otro superadmin
                        $badge = ['superadmin' => 'badge-danger', 'admin' => 'badge-dark', 'autor' => 'badge-secondary'][$r['rol']] ?? 'badge-secondary';
                    ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= e($nom) ?><?= $r['id'] == $yo['id'] ? ' <span class="badge badge-info">tú</span>' : '' ?></td>
                            <td><?= e($r['email']) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= e($r['rol']) ?></span></td>
                            <td class="text-nowrap"><?= e($r['created_at'] ?? '') ?></td>
                            <td class="text-right text-nowrap">
                                <?php if ($puedeTocar): ?>
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>"><i class="fas fa-edit"></i></a>
                                <?php else: ?>
                                <span class="text-muted small mr-2"><i class="fas fa-lock"></i></span>
                                <?php endif; ?>
                                <?php if ($r['id'] != $yo['id'] && $puedeTocar): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar a <?= e($r['email']) ?>?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small">
            Roles: <b>superadmin</b> (control total; <b>es el único que asigna o cambia roles</b>) &middot;
            <b>admin</b> (gestiona usuarios y contenido, y <b>publica</b>; no puede cambiar roles) &middot;
            <b>autor</b> (crea y edita solo <b>su</b> contenido; queda en <b>borrador</b> hasta que un admin lo publica).
        </p>

        <?php else:
            $d = $editar ?: ['id'=>0,'nombres'=>'','ap_paterno'=>'','ap_materno'=>'','email'=>'','rol'=>'autor'];
        ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card"><div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Nombres</label>
                                <input class="form-control" name="nombres" value="<?= e($d['nombres']) ?>" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Apellido paterno</label>
                                <input class="form-control" name="ap_paterno" value="<?= e($d['ap_paterno']) ?>" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Apellido materno</label>
                                <input class="form-control" name="ap_materno" value="<?= e($d['ap_materno'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label>Correo <small class="text-muted">(con este inicia sesión)</small></label>
                                <input type="email" class="form-control" name="email" value="<?= e($d['email']) ?>" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Rol</label>
                                <?php if ($soySuper): ?>
                                    <select class="form-control" name="rol">
                                        <?php foreach ($ROLES as $rol): ?>
                                            <option value="<?= $rol ?>" <?= ($d['rol'] ?? '') === $rol ? 'selected' : '' ?>><?= $rol ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input class="form-control" value="<?= e($d['id'] ? ($d['rol'] ?? '') : 'autor (nuevo usuario)') ?>" disabled>
                                    <small class="text-muted">Solo el <b>superadministrador</b> puede cambiar el rol.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group" style="max-width:320px">
                            <label>Contraseña <?= $d['id'] ? '<small class="text-muted">(dejar en blanco para no cambiarla)</small>' : '<small class="text-muted">(mínimo 6)</small>' ?></label>
                            <input type="password" class="form-control" name="password" autocomplete="new-password" <?= $d['id'] ? '' : 'required' ?>>
                        </div>
                    </div></div>
                    <button class="btn btn-primary">Guardar usuario</button>
                    <a class="btn btn-default" href="usuarios.php">Cancelar</a>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

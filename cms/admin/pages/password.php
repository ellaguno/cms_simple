<?php
/** Contraseña propia: desde 1.29 se cambia en Usuarios (sección "Tu contraseña"). Esta ruta solo redirige. */
declare(strict_types=1);
admin_redirect(admin_url('users'));

<?php
/**
 * cms_simple — ganchos mínimos para que un paquete (o el tema) intervenga en el núcleo sin tocarlo.
 *
 *   cms_on('nombre', $fn, $prio = 10)   registra una función (menor prioridad = antes)
 *   cms_apply('nombre', $valor, ...$x)  filtro: pasa $valor por cada función registrada y devuelve el resultado
 *   cms_do('nombre', ...$x)             acción: llama a cada función registrada, sin retorno
 *
 * Puntos de gancho del núcleo (se añade uno solo cuando un paquete real lo necesita):
 *   content    filtro  ($html, $ctx)     en cms_content(): el HTML de un campo html o de un bloque de texto, antes de
 *                                        dibujarlo. $ctx = cms_current(): 'type', 'item', 'page', 'lang' de la petición.
 *   head       acción  ($page)           al final de cms_head(): para emitir <style>, <script> o <meta> propios.
 *   item.save  acción  ($type, $item)    tras guardar un elemento desde el panel (o con cms_item_save()).
 *   admin.item.sidebar  acción ($type, $item)  en la barra lateral del editor de un elemento ya guardado (1.28):
 *                                        para botones o información propios del paquete (echo HTML).
 *   cron       acción  ($log)            cuando el cron del hosting llama a /_cms/cron?token=… (o php cms/cron.php).
 *                                        $log es una función ($mensaje) para dejar constancia en data/cron.json.
 *
 * Un paquete registra sus ganchos en <paquete>/inc.php, que el arranque carga una vez si el paquete está activo.
 * Además del manifiesto: 'admin' => ['label' => 'Audio', 'file' => 'admin.php'] da al paquete una página en el panel
 * (?p=pack:<nombre>, con $pack disponible), que aparece en el menú lateral.
 */
declare(strict_types=1);

function cms_on(string $hook, callable $fn, int $prio = 10): void
{
    $GLOBALS['cms_hooks'][$hook][$prio][] = $fn;
}

function cms_has_hook(string $hook): bool
{
    return !empty($GLOBALS['cms_hooks'][$hook]);
}

/** Lista de funciones de un gancho, ordenadas por prioridad. */
function cms_hook_fns(string $hook): array
{
    $h = (array) ($GLOBALS['cms_hooks'][$hook] ?? []);
    if (!$h) return [];
    ksort($h);
    return array_merge(...array_values($h));
}

function cms_apply(string $hook, $value, ...$args)
{
    foreach (cms_hook_fns($hook) as $fn) $value = $fn($value, ...$args);
    return $value;
}

function cms_do(string $hook, ...$args): void
{
    foreach (cms_hook_fns($hook) as $fn) $fn(...$args);
}

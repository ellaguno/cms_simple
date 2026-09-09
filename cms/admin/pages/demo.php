<?php
/**
 * Ejemplo en vivo de un bloque (?block=clave) o de un efecto (?effect=paquete/efecto): arma en memoria una página del
 * constructor con una sola sección y datos de muestra, y deja que el enrutador público la dibuje con el tema en modo
 * ?cmsbare=1 (sin cabecera ni pie). Lo usan el manual y el selector del constructor dentro de un iframe.
 */
declare(strict_types=1);
$type = cms_builder_type();
if ($type === null) { http_response_code(404); exit('Este tema no usa el constructor de secciones.'); }
$ek = (string) ($_GET['effect'] ?? '');
$bk = (string) ($_GET['block'] ?? '');
if ($ek !== '') { $ed = cms_effects()[$ek] ?? null; $sec = $ed ? cms_effect_sample($ed) : null; }
else { $bd = $bk !== '' ? cms_block($bk) : null; $sec = $bd ? ['id' => 'demo01', 'type' => (string) $bd['key'], 'data' => cms_block_sample($bd), 'style' => []] : null; }
if (!$sec) { http_response_code(404); exit('Bloque o efecto desconocido.'); }

$slug = 'ejemplo-en-vivo';
$item = ['slug' => $slug, 'status' => 'published', 'title' => 'Ejemplo', 'parent' => '', 'path' => $slug, 'order' => 999,
    'created' => date('Y-m-d'), 'updated' => date('Y-m-d'), 'sections' => [$sec]];
$GLOBALS['cms_item_override'] = [$type => [$slug => $item]];
cms_items_flush();

$lang = cms_default_lang();
$url = cms_url('item:' . $type, $lang, $slug);
$path = trim((string) parse_url($url, PHP_URL_PATH), '/');
if (CMS_BASE !== '' && strpos('/' . $path, CMS_BASE) === 0) $path = trim(substr('/' . $path, strlen(CMS_BASE)), '/');
$_GET = ['p' => $path, 'cmsbare' => '1'];
$_SERVER['REQUEST_METHOD'] = 'GET';
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: frame-ancestors \'self\'');
header('Cache-Control: no-store');
ob_start();
require CMS_DIR . '/router.php';
$html = ob_get_clean();
// avisa al panel de su altura (iframe escalado) y neutraliza los enlaces del ejemplo
$key = $ek !== '' ? $ek : (string) $sec['type'];
$js = '<script>(function(){var k=' . json_encode($key) . ';function h(){var s=document.querySelectorAll("section.sec"),b=0;s.forEach(function(e){var r=e.getBoundingClientRect();b=Math.max(b,r.bottom+window.scrollY)});return Math.ceil(b||document.documentElement.scrollHeight)+8}'
    . 'function send(){if(window.parent!==window)window.parent.postMessage({cmsDemo:k,height:h()},location.origin)}'
    . 'window.addEventListener("load",send);setTimeout(send,500);setTimeout(send,1500);setTimeout(send,3000);'
    . 'if(window.ResizeObserver)new ResizeObserver(send).observe(document.body);'
    . 'document.addEventListener("click",function(e){var a=e.target.closest("a");if(a)e.preventDefault()});'
    . 'document.addEventListener("submit",function(e){e.preventDefault()});})();</script>';
$pos = strripos($html, '</body>');
echo $pos !== false ? substr($html, 0, $pos) . $js . substr($html, $pos) : $html . $js;

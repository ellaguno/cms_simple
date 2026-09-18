<?php
/** Tema "Cadencia": hijo del lienzo (theme.json → "parent": "lienzo"). Toma del lienzo bloques, plantillas y layout; aporta su CSS (assets/css/tema.css), tipografías y contenido de muestra. */
$cfg = cms_parent_config();
$cfg['name'] = "Cadencia";
$cfg['theme_fonts'] = ["Plus Jakarta Sans", "Plus Jakarta Sans"];   // [texto, títulos] por defecto; Ajustes → Diseño las cambia
return $cfg;

<?php
/** Tema "Hélice": hijo del lienzo (theme.json → "parent": "lienzo"). Toma del lienzo bloques, plantillas y layout; aporta su CSS (assets/css/tema.css), tipografías y contenido de muestra. */
$cfg = cms_parent_config();
$cfg['name'] = "Hélice";
$cfg['theme_fonts'] = ["Space Grotesk", "Space Grotesk"];   // [texto, títulos] por defecto; Ajustes → Diseño las cambia
return $cfg;

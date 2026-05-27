<?php
// BORRAR ESTE ARCHIVO TRAS VERIFICAR
echo class_exists(Redis::class) ? 'Redis OK' : 'Redis NO disponible';
phpinfo(INFO_MODULES);
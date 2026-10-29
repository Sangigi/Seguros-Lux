/**
 * iconos.js — íconos SVG del sprite /svg/iconos.svg (Bootstrap Icons).
 *
 * Genera el mismo marcado que icono() en Core/helpers.php, para el HTML que
 * se arma en el navegador. El ícono toma el tamaño y el color del texto.
 */
window.iconoSvg = function (nombre, clase) {
    const clases = ('ico ' + (clase || '')).trim();
    return '<svg class="' + clases + '" width="1em" height="1em" fill="currentColor" aria-hidden="true"'
        + ' style="vertical-align:-.125em;flex-shrink:0">'
        + '<use href="/svg/iconos.svg#' + nombre + '"/></svg>';
};

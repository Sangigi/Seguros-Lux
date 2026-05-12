<?php
/**
 * Forzar jQuery nativo de WordPress en HTTPS
 */

add_action('wp_enqueue_scripts', function () {
    wp_deregister_script('jquery');
    wp_register_script(
        'jquery',
        includes_url('js/jquery/jquery.min.js'),
        [],
        null,
        true
    );
    wp_enqueue_script('jquery');
}, 1);

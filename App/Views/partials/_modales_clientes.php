<?php
/**
 * Partial: _modales_clientes.php — modales de Nota, Cita y Nuevo Cliente
 *
 * Variables esperadas del scope padre:
 *   $csrfToken  — string con el token CSRF
 *   $cspNonce   — string con el nonce CSP
 *   $ramoAlta   — clave del ramo en que se da de alta el cliente nuevo (la
 *                 pestaña de ramo de la página), o '' para que el servidor
 *                 elija. El servidor la valida contra los ramos activos.
 *
 * Los botones de guardar usan data-action en lugar de IDs para que
 * la delegación global de crm-events.js sea el ÚNICO listener.
 * Así se evita el doble registro que causaba duplicados en BD.
 */

$csrfToken ??= '';
$cspNonce  ??= '';
$ramoAlta  ??= '';
?>

<!-- Modal: Nota -->
<div class="modal-overlay" id="modalNota">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Agregar Nota</h3>
            <button class="modal-close" data-close="modalNota">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="notaClienteId">
            <div class="form-group">
                <label class="form-label required">Nota</label>
                <textarea class="form-textarea" id="notaTexto" rows="4"
                          placeholder="Escribe tu nota sobre el cliente..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalNota">Cancelar</button>
            <button class="btn btn-primary" data-action="guardarNota">Guardar Nota</button>
        </div>
    </div>
</div>

<!-- Modal: Cita -->
<div class="modal-overlay" id="modalCita">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Programar Cita</h3>
            <button class="modal-close" data-close="modalCita">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="citaClienteId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Fecha</label>
                    <input type="date" class="form-input" id="citaFecha"
                           min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label required">Hora</label>
                    <input type="time" class="form-input" id="citaHora" value="10:00">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Tipo de Cita</label>
                <select class="form-select" id="citaTipo">
                    <option value="llamada">Llamada telefónica</option>
                    <option value="videollamada">Videollamada</option>
                    <option value="presencial">Presencial</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Comentarios</label>
                <textarea class="form-textarea" id="citaComentarios" placeholder="Detalles de la cita..."></textarea>
            </div>
            <!-- Campos dinámicos por ramo (renderizados por clientes.js) -->
            <div id="citaCamposRamo"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalCita">Cancelar</button>
            <button class="btn btn-success" data-action="guardarCita">Programar Cita</button>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Cliente -->
<div class="modal-overlay" id="modalNuevoCliente">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Cliente</h3>
            <button class="modal-close" data-close="modalNuevoCliente">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoCliente" method="POST" action="/clientes">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="ramo" value="<?= htmlspecialchars((string) $ramoAlta, ENT_QUOTES) ?>">
                <p style="font-size:13px;font-weight:700;color:var(--accent-color);text-transform:uppercase;margin-bottom:14px;">Información Personal</p>
                <div class="form-row">
                    <div class="form-group"><label class="form-label required">Nombre</label><input type="text" class="form-input" name="nombre" required></div>
                    <div class="form-group"><label class="form-label required">Apellido Paterno</label><input type="text" class="form-input" name="apellido_paterno" required></div>
                    <div class="form-group"><label class="form-label">Apellido Materno</label><input type="text" class="form-input" name="apellido_materno"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label required">Correo</label><input type="email" class="form-input" name="correo" required></div>
                    <div class="form-group"><label class="form-label">Teléfono</label><input type="tel" class="form-input" name="telefono"></div>
                    <div class="form-group"><label class="form-label">Ciudad</label><input type="text" class="form-input" name="ciudad"></div>
                </div>
                <p style="font-size:13px;font-weight:700;color:var(--accent-color);text-transform:uppercase;margin:20px 0 14px;">Vehículo</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Marca</label>
                        <select class="form-select" name="marca" required>
                            <option value="">Selecciona marca…</option>
                            <?php
                            $MARCAS_MODAL = ['ACURA','ALFA ROMEO','AUDI','BMW','BUICK','CADILLAC','CHEVROLET','CHRYSLER','CUPRA','DACIA','DODGE','DS','FERRARI','FIAT','FORD','GMC','HONDA','HYUNDAI','INFINITI','JAGUAR','JEEP','KIA','LAMBORGHINI','LAND ROVER','LEXUS','LINCOLN','LOTUS','MASERATI','MAZDA','MERCEDES-BENZ','MINI','MITSUBISHI','NISSAN','OPEL','PEUGEOT','PORSCHE','RAM','RENAULT','ROLLS-ROYCE','SEAT','SKODA','SMART','SUBARU','SUZUKI','TESLA','TOYOTA','VOLKSWAGEN','VOLVO','BAIC','BYD','CHANGAN','CHIREY','GREAT WALL','HAVAL','JAC','JETOUR','MG','OTRA'];
                            foreach ($MARCAS_MODAL as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label required">Modelo</label><input type="text" class="form-input" name="modelo" required placeholder="Ej. Corolla, Jetta…"></div>
                    <div class="form-group">
                        <label class="form-label">Año</label>
                        <input type="number" class="form-input" name="anio_vehiculo"
                               min="1990" max="<?php echo date('Y')+1; ?>" value="<?php echo date('Y'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Submarca</label>
                        <input type="text" class="form-input" name="submarca" placeholder="Ej. LE, SE, Sport…">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Versión</label>
                        <input type="text" class="form-input" name="version" placeholder="Ej. 2.0T AT, CVT…">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo_vehiculo">
                            <option value="auto">Auto</option>
                            <option value="moto">Moto</option>
                            <option value="camioneta">Camioneta</option>
                            <option value="pickup">Pickup</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">¿Asegurado?</label>
                        <select class="form-select" name="asegurado">
                            <option value="no">No</option>
                            <option value="si">Sí</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">¿Cuándo comprar?</label>
                        <select class="form-select" name="tiempo_compra">
                            <option value="inmediato">Inmediato</option>
                            <option value="esta_semana">Esta semana</option>
                            <option value="este_mes">Este mes</option>
                            <option value="solo_cotizando">Solo cotizando</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:8px;">
                    <label class="form-label" style="font-weight:600;">
                        ¿Cuenta con alguna adaptación o modificación? <span style="font-weight:400;color:var(--text-muted);">(opcional)</span>
                    </label>
                    <textarea class="form-input" name="detalle_adaptaciones" rows="2"
                              placeholder="¿Cuentas con alguna adaptación o modificación? &#10;¿Quieres agregar algo más?"
                              style="resize:vertical;"></textarea>
                    <input type="hidden" name="tiene_adaptaciones" id="hdn_tiene_adaptaciones" value="0">
                </div>
                <script nonce="<?= $cspNonce ?>">
                (function(){
                    var ta = document.querySelector('[name="detalle_adaptaciones"]');
                    var hdn = document.getElementById('hdn_tiene_adaptaciones');
                    if(ta && hdn) ta.addEventListener('input', function(){ hdn.value = ta.value.trim() ? '1' : '0'; });
                })();
                </script>
                <div class="form-row" style="margin-top:8px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de cita</label>
                        <input type="date" class="form-input" name="fecha_cita" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora</label>
                        <input type="time" class="form-input" name="hora_cita" value="10:00">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalNuevoCliente">Cancelar</button>
            <button class="btn btn-primary" data-action="guardarCliente">Guardar Cliente</button>
        </div>
    </div>
</div>
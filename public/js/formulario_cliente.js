/**
 * formulario_cliente.js — CRM Seguros Lux
 * JS del formulario vehicular público (form_client.php)
 * Corregido: selectTipoVehiculo sin radio oculto, showStep sin crash null,
 *            prevStep con fallback si volverASeleccionRamo no existe.
 */

/* ═══════════════════════════════════════════════════
   CATÁLOGO DE MARCAS POR TIPO DE VEHÍCULO
═══════════════════════════════════════════════════ */
var MARCAS_POR_TIPO = {
    auto: [
        'Audi','BMW','Chevrolet','Ford','Honda',
        'Hyundai','Jeep','KIA','Mazda','Mercedes-Benz',
        'Mitsubishi','Nissan','Peugeot','Porsche','Renault',
        'SEAT','Suzuki','Toyota','Volkswagen','Volvo','Otra'
    ],
    moto: [
        'BMW Motorrad','Ducati','Harley-Davidson','Honda',
        'Kawasaki','KTM','Royal Enfield','Suzuki',
        'Triumph','Yamaha','Otra'
    ],
    camioneta: [
        'Audi','BMW','Chevrolet','Ford','Honda',
        'Hyundai','Jeep','KIA','Land Rover','Mazda',
        'Mercedes-Benz','Mitsubishi','Nissan','Suzuki',
        'Toyota','Volkswagen','Volvo','Otra'
    ],
    pickup: [
        'Chevrolet','Ford','GMC','Isuzu','Mazda',
        'Mitsubishi','Nissan','RAM','Toyota','Volkswagen','Otra'
    ]
};

/* Estado global del formulario */
var currentStepNum   = 0;
var totalSteps       = 7;
var selectedDate     = null;
var selectedTime     = null;
var selectedTipoCita = null;
var selectedCanal    = null;
var calendarMonth    = new Date().getMonth();
var calendarYear     = new Date().getFullYear();

var radioSelections = {
    sexo:          null,
    tipoVehiculo:  null,
    recienComprado:null,
    asegurado:     null,
    tiempoCompra:  null
};

var miniFormData = {};

var MONTHS = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
];

var PHONE_NUMBER = '5215512345678';

/* ═══════════════════════════════════════════════════
   INICIALIZACIÓN
═══════════════════════════════════════════════════ */
(function init() {
    populateYears();
    renderCalendar();
    renderTimeSlots();
    setupBirthDateListener();
    setupInputClearErrors();
    autoSelectToday();
})();

function autoSelectToday() {
    var hoy = new Date(); hoy.setHours(0,0,0,0);
    if (hoy.getDay() === 0) return;
    var yyyy = hoy.getFullYear();
    var mm   = String(hoy.getMonth()+1).padStart(2,'0');
    var dd   = String(hoy.getDate()).padStart(2,'0');
    var dateStr = yyyy+'-'+mm+'-'+dd;
    selectedDate = dateStr;
    cargarHorasOcupadas(dateStr);
    setTimeout(function() {
        var cell = document.querySelector('.day-cell[data-date="'+dateStr+'"]');
        if (cell) cell.classList.add('selected');
    }, 0);
}

function populateYears() {
    var sel = document.getElementById('anioVehiculo');
    if (!sel) return;
    var currentYear = new Date().getFullYear();
    for (var y = currentYear + 1; y >= currentYear - 30; y--) {
        var opt = document.createElement('option');
        opt.value = y; opt.textContent = y;
        sel.appendChild(opt);
    }
}

function setupBirthDateListener() {
    var input = document.getElementById('fechaNacimiento');
    if (!input) return;
    input.addEventListener('change', function () {
        var dob   = new Date(this.value);
        var today = new Date();
        var age   = today.getFullYear() - dob.getFullYear();
        var m     = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        var edadEl = document.getElementById('edad');
        if (edadEl) edadEl.value = age > 0 ? age + ' años' : '';
    });
}

function setupInputClearErrors() {
    document.querySelectorAll('.form-input').forEach(function (input) {
        ['input', 'change'].forEach(function (evt) {
            input.addEventListener(evt, function () {
                this.classList.remove('error');
                var errEl = document.getElementById('error-' + this.id);
                if (errEl) errEl.classList.remove('show');
            });
        });
    });
}

/* ═══════════════════════════════════════════════════
   MARCAS DINÁMICAS — TIPO DE VEHÍCULO
   FIX: no intentar acceder a radio oculto que puede no existir
═══════════════════════════════════════════════════ */
function selectTipoVehiculo(tipo, el) {
    var parent = el.parentNode;
    parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');

    /* El radio es opcional — algunas cards no lo tienen */
    var radio = el.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;

    radioSelections.tipoVehiculo = tipo;
    clearError('tipoVehiculo');

    actualizarMarcas(tipo);

    var miniMap = {
        auto:      'miniFormAuto',
        moto:      'miniFormMoto',
        camioneta: 'miniFormCamioneta',
        pickup:    'miniFormPickup'
    };
    Object.values(miniMap).forEach(function (id) {
        var el2 = document.getElementById(id);
        if (el2) el2.style.display = 'none';
    });
    miniFormData = {};

    /* Mostrar wrapper de datos del vehículo */
    var wrapper = document.getElementById('vehDatosWrapper');
    if (wrapper) wrapper.style.display = 'block';

    var target = document.getElementById(miniMap[tipo]);
    if (target) {
        target.style.display = 'block';
        setTimeout(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 50);
    }
}

function actualizarMarcas(tipo) {
    var sel = document.getElementById('marca');
    if (!sel) return;
    sel.innerHTML = '<option value="">Selecciona una marca</option>';
    var lista = (MARCAS_POR_TIPO[tipo] || []).slice().sort();
    lista.forEach(function (m) {
        var o = document.createElement('option');
        o.value = m.toLowerCase().replace(/[\s/-]+/g, '_');
        o.textContent = m;
        sel.appendChild(o);
    });
}

/* ═══════════════════════════════════════════════════
   MINI-FORM: TOGGLE PILLS
═══════════════════════════════════════════════════ */
function selectMiniTog(group, el) {
    var container = el.parentNode;
    container.querySelectorAll('.mini-tog').forEach(function (t) { t.classList.remove('selected'); });
    el.classList.add('selected');
    miniFormData[group] = el.getAttribute('data-val');
}

/* ═══════════════════════════════════════════════════
   TIPO DE CITA
═══════════════════════════════════════════════════ */
function selectTipoCita(tipo, el) {
    var cards = document.querySelectorAll('#tipoCitaOptions .option-card');
    cards.forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');
    selectedTipoCita = tipo;
    clearError('tipoCita');
}

/* ═══════════════════════════════════════════════════
   OPTION CARDS GENÉRICAS (sexo, recienComprado, asegurado, tiempoCompra)
   FIX: radio también es opcional aquí
═══════════════════════════════════════════════════ */
function selectOption(groupName, el) {
    var parent = el.parentNode;
    parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');

    var radio = el.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;

    radioSelections[groupName] = el.getAttribute('data-value');
    var errEl = document.getElementById('error-' + groupName);
    if (errEl) errEl.classList.remove('show');
}

/* ═══════════════════════════════════════════════════
   NAVEGACIÓN ENTRE PASOS
   FIX: showStep no hace getElementById('formVidaGmm') directamente
        — usa getElementById con null-check para ambos nombres posibles
═══════════════════════════════════════════════════ */
function showStep(n) {
    /* Ocultar form vida/gmm — soporta ambos IDs posibles */
    var vidaEl = document.getElementById('formVidaGmm') || document.getElementById('ramoVidaGmm');
    if (vidaEl) vidaEl.style.display = 'none';

    document.querySelectorAll('.form-step').forEach(function (s) { s.classList.remove('active'); });

    var stepAttr = (n === '5b' || n === 5.5) ? '5b' : String(n);
    var target = document.querySelector('.form-step[data-step="' + stepAttr + '"]');
    if (target) target.classList.add('active');

    var counterEl = document.getElementById('currentStep');
    var scEl      = document.querySelector('.step-counter');
    var pbEl      = document.getElementById('progressBar');

    var displayMap = { '0':1, '1':2, '2':3, '3':4, '4':5, '5':6, '5b':7, '6':null };
    var displayN   = displayMap[stepAttr];

    if (displayN !== null && displayN !== undefined) {
        if (scEl)      scEl.style.display  = 'block';
        if (pbEl)      pbEl.style.display  = 'flex';
        if (counterEl) counterEl.textContent = displayN;
    } else {
        if (scEl) scEl.style.display = 'none';
        if (pbEl) pbEl.style.display = 'none';
    }

    if (displayN) {
        document.querySelectorAll('.progress-segment').forEach(function (seg, i) {
            seg.classList.remove('active', 'done');
            if (i < displayN - 1) seg.classList.add('done');
            if (i === displayN - 1) seg.classList.add('active');
        });
    }

    currentStepNum = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep(fromStep) {
    if (!validateStep(fromStep)) return;
    if (fromStep === 5) {
        showStep('5b');
    } else {
        showStep(fromStep + 1);
    }
}

function prevStep(fromStep) {
    if (fromStep === 1) {
        /* FIX: fallback si volverASeleccionRamo no está definido en el inline */
        if (typeof volverASeleccionRamo === 'function') {
            volverASeleccionRamo();
        } else {
            document.querySelectorAll('.ramo-form,[id^="ramo"]').forEach(function(f){ f.style.display='none'; });
            var empty = document.getElementById('formEmpty');
            if (empty) empty.style.display = 'block';
        }
    } else {
        showStep(fromStep - 1);
    }
}

/* ═══════════════════════════════════════════════════
   VALIDACIÓN POR PASO
═══════════════════════════════════════════════════ */
function showError(fieldId) {
    var input = document.getElementById(fieldId);
    var err   = document.getElementById('error-' + fieldId);
    if (input) input.classList.add('error');
    if (err)   err.classList.add('show');
}

function clearError(fieldId) {
    var input = document.getElementById(fieldId);
    var err   = document.getElementById('error-' + fieldId);
    if (input) input.classList.remove('error');
    if (err)   err.classList.remove('show');
}

function validateStep(step) {
    var valid = true;

    if (step === 1) {
        ['nombre','apellidoPaterno','apellidoMaterno','fechaNacimiento','estadoCivil'].forEach(function (f) {
            clearError(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showError(f); valid = false; }
        });

        clearError('correo');
        var correoEl = document.getElementById('correo');
        var correo = correoEl ? correoEl.value.trim() : '';
        if (!correo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) { showError('correo'); valid = false; }

        clearError('telefono');
        var telEl = document.getElementById('telefono');
        var tel = (telEl ? telEl.value.trim() : '').replace(/\D/g, '');
        if (!tel || tel.length < 8) { showError('telefono'); valid = false; }

        clearError('sexo');
        if (!radioSelections.sexo) { showError('sexo'); valid = false; }
    }

    if (step === 2) {
        ['calle','colonia','codigoPostal','ciudad','estado'].forEach(function (f) {
            clearError(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showError(f); valid = false; }
        });
    }

    if (step === 3) {
        clearError('tipoVehiculo');
        if (!radioSelections.tipoVehiculo) { showError('tipoVehiculo'); valid = false; }

        ['marca','modelo','anioVehiculo'].forEach(function (f) {
            clearError(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showError(f); valid = false; }
        });

        clearError('recienComprado');
        if (!radioSelections.recienComprado) { showError('recienComprado'); valid = false; }
    }

    if (step === 4) {
        clearError('asegurado');
        if (!radioSelections.asegurado) { showError('asegurado'); valid = false; }

        clearError('tiempoCompra');
        if (!radioSelections.tiempoCompra) { showError('tiempoCompra'); valid = false; }
    }

    if (step === 5) {
        clearError('tipoCita');
        if (!selectedTipoCita) { showError('tipoCita'); valid = false; }

        clearError('fecha');
        if (!selectedDate) { showError('fecha'); valid = false; }

        clearError('hora');
        if (!selectedTime) { showError('hora'); valid = false; }
    }

    return valid;
}

/* ═══════════════════════════════════════════════════
   CALENDARIO
═══════════════════════════════════════════════════ */
function renderCalendar() {
    var grid = document.getElementById('calendarGrid');
    if (!grid) return;
    grid.innerHTML = '';

    var titleEl = document.getElementById('calendarTitle');
    if (titleEl) titleEl.textContent = MONTHS[calendarMonth] + ' ' + calendarYear;

    ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].forEach(function (d) {
        var cell = document.createElement('div');
        cell.className = 'day-name'; cell.textContent = d;
        grid.appendChild(cell);
    });

    var firstDay    = new Date(calendarYear, calendarMonth, 1).getDay();
    var daysInMonth = new Date(calendarYear, calendarMonth + 1, 0).getDate();
    var today       = new Date(); today.setHours(0,0,0,0);
    var todayStr    = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');

    for (var i = 0; i < firstDay; i++) {
        var empty = document.createElement('div');
        empty.className = 'day-cell empty';
        grid.appendChild(empty);
    }

    for (var d = 1; d <= daysInMonth; d++) {
        var cell    = document.createElement('div');
        cell.className   = 'day-cell';
        cell.textContent = d;

        var cellDate = new Date(calendarYear, calendarMonth, d);
        var dow      = cellDate.getDay();
        var dateStr  = calendarYear+'-'+String(calendarMonth+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');

        if (dateStr < todayStr || dow === 0) {
            cell.classList.add('disabled');
        } else {
            cell.setAttribute('data-date', dateStr);
            cell.addEventListener('click', function () {
                if (this.classList.contains('disabled')) return;
                document.querySelectorAll('.day-cell.selected').forEach(function (c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                selectedDate = this.getAttribute('data-date');
                clearError('fecha');
                cargarHorasOcupadas(selectedDate);
            });
        }

        if (dateStr === todayStr) cell.classList.add('today');
        if (selectedDate === dateStr) cell.classList.add('selected');
        grid.appendChild(cell);
    }
}

function changeMonth(dir) {
    calendarMonth += dir;
    if (calendarMonth > 11) { calendarMonth = 0; calendarYear++; }
    if (calendarMonth < 0)  { calendarMonth = 11; calendarYear--; }

    var now = new Date();
    if (calendarYear < now.getFullYear() ||
        (calendarYear === now.getFullYear() && calendarMonth < now.getMonth())) {
        calendarMonth = now.getMonth();
        calendarYear  = now.getFullYear();
    }
    renderCalendar();
}

/* ═══════════════════════════════════════════════════
   HORARIOS
═══════════════════════════════════════════════════ */
function renderTimeSlots() {
    var container = document.getElementById('timeSlots');
    if (!container) return;
    container.innerHTML = '';
    var times = [
        '09:00','09:30','10:00','10:30',
        '11:00','11:30','12:00','12:30',
        '13:00','13:30','14:00','14:30',
        '16:00','16:30','17:00','17:30'
    ];
    times.forEach(function (t) {
        var slot = document.createElement('div');
        slot.className = 'time-slot';
        slot.textContent = t;
        slot.setAttribute('data-time', t);
        slot.addEventListener('click', function () {
            if (this.classList.contains('disabled')) return;
            document.querySelectorAll('.time-slot.selected').forEach(function (s) { s.classList.remove('selected'); });
            this.classList.add('selected');
            selectedTime = this.getAttribute('data-time');
            clearError('hora');
        });
        container.appendChild(slot);
    });
}

function cargarHorasOcupadas(fecha) {
    selectedTime = null;
    document.querySelectorAll('.time-slot').forEach(function (s) { s.classList.remove('selected','disabled'); });

    var container = document.getElementById('timeSlots');
    if (container) container.style.opacity = '0.5';

    var hoyStr  = new Date().toISOString().split('T')[0];
    var esHoy   = (fecha === hoyStr);
    var ahora   = new Date();
    ahora.setMinutes(ahora.getMinutes() + 30);
    var horaLim = String(ahora.getHours()).padStart(2,'0') + ':' + String(ahora.getMinutes()).padStart(2,'0');

    fetch('/horas-ocupadas?fecha=' + fecha)
        .then(function (r) { return r.json(); })
        .then(function (ocupadas) {
            if (container) container.style.opacity = '1';
            document.querySelectorAll('.time-slot').forEach(function (slot) {
                var hora    = slot.getAttribute('data-time');
                var ocupada = ocupadas.indexOf(hora) !== -1;
                var pasada  = esHoy && hora <= horaLim;
                if (ocupada || pasada) {
                    slot.classList.add('disabled');
                    slot.title = ocupada ? 'Hora no disponible' : 'Hora ya pasada';
                }
            });
        })
        .catch(function () {
            if (container) container.style.opacity = '1';
            if (esHoy) {
                document.querySelectorAll('.time-slot').forEach(function (slot) {
                    if (slot.getAttribute('data-time') <= horaLim) {
                        slot.classList.add('disabled');
                        slot.title = 'Hora ya pasada';
                    }
                });
            }
        });
}

/* ═══════════════════════════════════════════════════
   COMENTARIOS DEL MINI-FORM
═══════════════════════════════════════════════════ */
function buildComentariosMiniForm() {
    if (!Object.keys(miniFormData).length) return '';

    var etiquetas = {
        quemacocos:  'Quemacocos',
        rines:       'Rines de lujo',
        uso_auto:    'Uso del auto',
        gps:         'GPS instalado',
        cilindrada:  'Cilindrada',
        tipo_moto:   'Tipo de moto',
        uso_moto:    'Uso delivery',
        mod_moto:    'Modificaciones',
        uso_suv:     'Uso SUV',
        techo_suv:   'Techo panorámico',
        trac_suv:    'Tracción SUV',
        viaje_suv:   'Viajes carretera',
        uso_pickup:  'Uso pickup',
        acces_pickup:'Accesorios pickup',
        trac_pickup: 'Tracción pickup'
    };

    var lineas = ['[Datos adicionales del vehículo]'];
    Object.keys(miniFormData).forEach(function (k) {
        var label = etiquetas[k] || k;
        lineas.push(label + ': ' + miniFormData[k]);
    });
    return lineas.join(' | ');
}

/* ═══════════════════════════════════════════════════
   ENVÍO DEL FORMULARIO
═══════════════════════════════════════════════════ */
function submitForm() {
    if (typeof selectedCanal === 'undefined' || !selectedCanal) {
        var errCanal = document.getElementById('error-canal');
        if (errCanal) errCanal.classList.add('show');
        return;
    }

    var nombreEl   = document.getElementById('nombre');
    var apPatEl    = document.getElementById('apellidoPaterno');
    var apMatEl    = document.getElementById('apellidoMaterno');
    var correoEl   = document.getElementById('correo');
    var edadEl     = document.getElementById('edad');
    var marcaSel   = document.getElementById('marca');
    var modeloEl   = document.getElementById('modelo');
    var anioEl     = document.getElementById('anioVehiculo');
    var calleEl    = document.getElementById('calle');
    var coloniaEl  = document.getElementById('colonia');
    var ciudadEl   = document.getElementById('ciudad');
    var cpEl       = document.getElementById('codigoPostal');
    var estadoSel  = document.getElementById('estado');
    var ecSel      = document.getElementById('estadoCivil');
    var ladaEl     = document.getElementById('lada');
    var telEl      = document.getElementById('telefono');

    var nombre    = nombreEl   ? nombreEl.value.trim()   : '';
    var apPat     = apPatEl    ? apPatEl.value.trim()    : '';
    var apMat     = apMatEl    ? apMatEl.value.trim()    : '';
    var correo    = correoEl   ? correoEl.value.trim()   : '';
    var edad      = edadEl     ? edadEl.value            : '';
    var marcaText = marcaSel   ? marcaSel.options[marcaSel.selectedIndex].text : '';
    var modelo    = modeloEl   ? modeloEl.value.trim()   : '';
    var anio      = anioEl     ? anioEl.value            : '';
    var calle     = calleEl    ? calleEl.value.trim()    : '';
    var colonia   = coloniaEl  ? coloniaEl.value.trim()  : '';
    var ciudad    = ciudadEl   ? ciudadEl.value.trim()   : '';
    var cp        = cpEl       ? cpEl.value.trim()       : '';
    var estadoText= estadoSel  ? estadoSel.options[estadoSel.selectedIndex].text : '';
    var ecText    = ecSel      ? ecSel.options[ecSel.selectedIndex].text : '';

    var parts          = (selectedDate||'').split('-');
    var fechaFormatted = parts[2] + ' de ' + MONTHS[parseInt(parts[1]) - 1] + ' ' + parts[0];
    var comentarioExtra = buildComentariosMiniForm();

    var formData = {
        nombre:           nombre,
        apellidoPaterno:  apPat,
        apellidoMaterno:  apMat,
        fechaNacimiento:  (document.getElementById('fechaNacimiento')||{value:''}).value,
        correo:           correo,
        sexo:             radioSelections.sexo,
        estadoCivil:      ecSel ? ecSel.value : '',
        calle:            calle,
        colonia:          colonia,
        ciudad:           ciudad,
        estado:           estadoSel ? estadoSel.value : '',
        codigoPostal:     cp,
        marca:            marcaSel ? marcaSel.value : '',
        modelo:           modelo,
        anioVehiculo:     anio,
        tipoVehiculo:     radioSelections.tipoVehiculo,
        recienComprado:   radioSelections.recienComprado,
        asegurado:        radioSelections.asegurado,
        tiempoCompra:     radioSelections.tiempoCompra,
        tipoCita:         selectedTipoCita,
        telefono:         '+' + (ladaEl?ladaEl.value:'52') + (telEl?telEl.value.trim():''),
        fechaCita:        selectedDate,
        horaCita:         selectedTime,
        canalNotificacion:selectedCanal,
        comentarios:      comentarioExtra
    };

    /* Rellenar resumen de confirmación con null-checks */
    function setConf(id, val) { var el=document.getElementById(id); if(el) el.textContent=val; }
    setConf('conf-nombre',    nombre + ' ' + apPat + ' ' + apMat);
    setConf('conf-correo',    correo);
    setConf('conf-telefono',  '+' + (ladaEl?ladaEl.value:'52') + ' ' + (telEl?telEl.value.trim():''));
    setConf('conf-edad',      edad || 'No indicada');
    setConf('conf-sexo',      capitalize(radioSelections.sexo));
    setConf('conf-ecivil',    ecText);
    setConf('conf-direccion', calle + ', ' + colonia);
    setConf('conf-ciudad',    ciudad + ', ' + estadoText);
    setConf('conf-cp',        cp);
    setConf('conf-tipo',      capitalize(radioSelections.tipoVehiculo));
    setConf('conf-auto',      marcaText + ' ' + modelo + ' ' + anio);
    setConf('conf-recien',    radioSelections.recienComprado === 'si' ? 'Sí' : 'No');
    setConf('conf-asegurado', radioSelections.asegurado === 'si' ? 'Sí' : 'No');
    setConf('conf-tipo-cita', selectedTipoCita === 'videollamada' ? '💻 Videollamada' : '📞 Llamada telefónica');
    setConf('conf-fecha',     fechaFormatted);
    setConf('conf-hora',      (selectedTime||'') + ' hrs');

    var canalLabels = { email: '✉️ Correo electrónico', whatsapp: '💬 WhatsApp', telegram: '✈️ Telegram' };
    setConf('conf-canal', canalLabels[selectedCanal] || selectedCanal);

    fetch('/api/clientes', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(formData)
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data.success) {
            showStep(6);
            mostrarMensajePersonalizado(data);
            mostrarCanalBanner(data, nombre, correo, fechaFormatted);

            if (selectedCanal === 'whatsapp' && data.whatsapp_link) {
                var waSec = document.getElementById('whatsappBotSection');
                var waBtn = document.getElementById('whatsappBotBtn');
                if (waSec && waBtn) { waBtn.href = data.whatsapp_link; waSec.style.display = 'block'; }
            }
            if (selectedCanal === 'telegram' && data.telegram_link) {
                var tgSec = document.getElementById('telegramSection');
                var tgBtn = document.getElementById('telegramBtn');
                if (tgSec && tgBtn) { tgBtn.href = data.telegram_link; tgSec.style.display = 'block'; }
            }
        } else {
            alert('Error: ' + (data.message || 'Ocurrió un error. Por favor intenta de nuevo.'));
        }
    })
    .catch(function (error) {
        console.error('Error:', error);
        showStep(6);
    });
}

/* ═══════════════════════════════════════════════════
   BANNER DE CANAL
═══════════════════════════════════════════════════ */
function mostrarCanalBanner(data, nombre, correo, fechaFormatted) {
    var bannerEl = document.getElementById('canalEnviadoBanner');
    if (!bannerEl) return;
    bannerEl.className = '';
    bannerEl.style.display = 'block';

    if (selectedCanal === 'email') {
        bannerEl.classList.add('email-banner');
        bannerEl.innerHTML = '✉️ <strong>¡Confirmación enviada a tu correo!</strong><br>Hemos enviado los detalles a <strong>' + correo + '</strong>.<br><small style="opacity:.8;">Si no lo encuentras, revisa tu carpeta de spam.</small>';
    } else if (selectedCanal === 'whatsapp') {
        bannerEl.classList.add('wa-banner');
        bannerEl.innerHTML = '💬 <strong>¡Abre WhatsApp para gestionar tu cita!</strong><br>Nuestro asistente reconocerá tu cita automáticamente.';
    } else if (selectedCanal === 'telegram') {
        bannerEl.classList.add('tg-banner');
        bannerEl.innerHTML = '✈️ <strong>¡Abre Telegram para gestionar tu cita!</strong><br>El bot confirmará tu cita al instante.';
    }
}

function mostrarMensajePersonalizado(data) {
    var msgEl = document.getElementById('mensajePersonalizado');
    if (!msgEl) return;

    if (data.cliente_existente && data.tiene_cita_activa) {
        msgEl.innerHTML = '⚠️ <strong>¡Ya tienes una cita activa con nosotros!</strong><br>Puedes gestionarla desde el bot o comunícate con tu asesor.';
        msgEl.style.cssText = 'display:block;background:#fff3e0;color:#e65100;border:1px solid #ffcc80;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;line-height:1.6;text-align:center;';
        var ico = document.getElementById('iconoConfirmacion');
        var tit = document.getElementById('tituloConfirmacion');
        if (ico) { ico.className = 'bi bi-exclamation-circle'; ico.style.color = '#f59e0b'; }
        if (tit) tit.textContent = '¡Ya tienes una cita!';
    } else if (data.cliente_existente) {
        msgEl.innerHTML = '👋 <strong>¡Bienvenido de vuelta!</strong><br>Hemos agendado tu nueva cita. ¡Nos da gusto verte de nuevo!';
        msgEl.style.cssText = 'display:block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;line-height:1.6;text-align:center;';
    } else {
        msgEl.innerHTML = '🎉 <strong>¡Registro exitoso!</strong><br>Tu cita ha sido agendada. En breve un asesor se pondrá en contacto contigo.';
        msgEl.style.cssText = 'display:block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;line-height:1.6;text-align:center;';
    }
}

/* ═══════════════════════════════════════════════════
   UTILIDADES
═══════════════════════════════════════════════════ */
function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/* ═══════════════════════════════════════════════════
   CANAL DE NOTIFICACIÓN
═══════════════════════════════════════════════════ */
function selectCanal(canal, el) {
    document.querySelectorAll('#canalOptions .canal-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');
    selectedCanal = canal;
    var errEl = document.getElementById('error-canal');
    if (errEl) errEl.classList.remove('show');
}

/* ═══════════════════════════════════════════════════
   ACCIONES DEL PASO DE CONFIRMACIÓN
═══════════════════════════════════════════════════ */
function sendWhatsApp() {
    var nombreEl = document.getElementById('conf-nombre');
    var fechaEl  = document.getElementById('conf-fecha');
    var horaEl   = document.getElementById('conf-hora');
    var nombre = nombreEl ? nombreEl.textContent : '';
    var fecha  = fechaEl  ? fechaEl.textContent  : '';
    var hora   = horaEl   ? horaEl.textContent   : '';
    var msg = encodeURIComponent(
        'Hola, acabo de registrar una cita en Seguros LUX.\n' +
        'Nombre: ' + nombre + '\n' +
        'Fecha: ' + fecha + '\n' +
        'Hora: ' + hora
    );
    window.open('https://wa.me/' + PHONE_NUMBER + '?text=' + msg, '_blank');
}

function sendEmail() {
    var correoEl = document.getElementById('conf-correo');
    var fechaEl  = document.getElementById('conf-fecha');
    var horaEl   = document.getElementById('conf-hora');
    var nombreEl = document.getElementById('conf-nombre');
    var correo = correoEl ? correoEl.textContent : '';
    var fecha  = fechaEl  ? fechaEl.textContent  : '';
    var hora   = horaEl   ? horaEl.textContent   : '';
    var nombre = nombreEl ? nombreEl.textContent : '';
    var subject = encodeURIComponent('Confirmación de cita — Seguros LUX');
    var body    = encodeURIComponent(
        'Hola,\n\nTe escribo para confirmar mi cita en Seguros LUX:\n\n' +
        'Nombre: ' + nombre + '\n' +
        'Fecha: ' + fecha + '\n' +
        'Hora: ' + hora + '\n\n¡Gracias!'
    );
    window.location.href = 'mailto:' + correo + '?subject=' + subject + '&body=' + body;
}
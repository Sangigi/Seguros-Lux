/**
 * formulario_cliente.js — CRM Seguros Lux
 * JS del formulario vehicular público
 * Simplificado: sin canal de notificación, sin paso 5b, flujo de 5 pasos.
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

/* Estado global */
var currentStepNum   = 0;
var selectedDate     = null;
var selectedTime     = null;
var selectedTipoCita = null;
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
═══════════════════════════════════════════════════ */
function selectTipoVehiculo(tipo, el) {
    var parent = el.parentNode;
    parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');

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
   OPTION CARDS GENÉRICAS
═══════════════════════════════════════════════════ */
function selectOption(groupName, el) {
    var parent = el.parentNode;
    parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');

    radioSelections[groupName] = el.getAttribute('data-value');
    var errEl = document.getElementById('error-' + groupName);
    if (errEl) errEl.classList.remove('show');
}

/* ═══════════════════════════════════════════════════
   VALIDACIÓN
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

        var dow     = new Date(calendarYear, calendarMonth, d).getDay();
        var dateStr = calendarYear+'-'+String(calendarMonth+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');

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
        .then(function (data) {
            var ocupadas = Array.isArray(data) ? data : (data && data.horas ? data.horas : []);
            if (!Array.isArray(ocupadas)) ocupadas = [];
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
        uso_suv:     'Uso SUV',
        trac_suv:    'Tracción SUV',
        uso_pickup:  'Uso pickup',
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
   UTILIDADES
═══════════════════════════════════════════════════ */
function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
/**
 * combobox-dynamic.js — Gestión dinámica de valores en combobox
 * Solicitante: solo confirmación (sin admin)
 * Otros: requiere admin password
 */

const fieldProtected = ['requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'];

// Inicializar combobox con detectores de nuevos valores
function initializeCombobox(fieldName, selectElement) {
    const isProtected = fieldProtected.includes(fieldName);
    const isSolicitante = fieldName === 'solicitante';
    
    const tomSelect = new TomSelect(selectElement, {
        create: true,  // ← Permitir crear nuevos valores
        maxOptions: 500,
        sortField: { field: 'text', direction: 'asc' }
    });
    
    // Guardar valores originales en el select
    const opcionesOriginales = new Set(Array.from(selectElement.options).map(o => o.value));
    
    // Escuchar cuando se intenta agregar un nuevo valor con ENTER
    tomSelect.on('item_add', function(value, item) {
        // Si el valor ya estaba en la lista original, permitir (es solo selección)
        if (opcionesOriginales.has(value)) {
            return; // Permitir selección normal
        }
        
        // Es un valor NUEVO - interceptar y mostrar confirmación
        // Remover inmediatamente (para evitar que se agregue sin confirmar)
        tomSelect.removeItem(value, true);
        tomSelect.clearOptions();
        
        // Recargar opciones
        Array.from(selectElement.options).forEach(opt => {
            if (opt.value) tomSelect.addOption({ value: opt.value, text: opt.text });
        });
        
        // Mostrar modal de confirmación según el tipo
        if (isSolicitante) {
            handleNuevoSolicitante(fieldName, value, tomSelect, null);
        } else if (isProtected) {
            handleNuevoValorAdmin(fieldName, value, tomSelect, null);
        }
    });
    
    return tomSelect;
}

// Manejo de nuevo solicitante (sin admin)
function handleNuevoSolicitante(field, valor, tomSelect, inputElement) {
    const modal = document.createElement('div');
    modal.id = 'modalConfirmacion';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-green-700 mb-3">✅ Nuevo Solicitante</h3>
            <p class="text-gray-700 mb-6">
                ¿Agregar a <strong>"${valor}"</strong> como nuevo solicitante al sistema?
            </p>
            <div class="flex gap-3">
                <button class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-2 px-4 rounded transition btnNo">
                    No
                </button>
                <button class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded transition btnSi">
                    Sí, agregar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    modal.querySelector('.btnNo').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.querySelector('.btnSi').addEventListener('click', () => {
        agregarSolicitanteFinal(field, valor, modal, tomSelect);
    });
}

// Manejo de nuevo valor con admin
function handleNuevoValorAdmin(field, valor, tomSelect, inputElement) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 mb-3">¿Agregar nueva variable?</h3>
            <p class="text-gray-700 mb-6">
                ¿Agregar <strong>"${valor}"</strong> al campo <strong>${field}</strong>?
            </p>
            <div class="flex gap-3">
                <button class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-2 px-4 rounded transition btnNo">
                    No
                </button>
                <button class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded transition btnSi">
                    Sí
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    modal.querySelector('.btnNo').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.querySelector('.btnSi').addEventListener('click', () => {
        modal.remove();
        solicitarLoginAdmin(field, valor, tomSelect);
    });
}

// Modal de login admin
function solicitarLoginAdmin(field, valor, tomSelect) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 mb-4">🔐 Acceso Administrador</h3>
            <p class="text-gray-600 mb-4 text-sm">
                Ingresa contraseña de administrador para agregar <strong>"${valor}"</strong>
            </p>
            <input type="password" id="adminPassword" placeholder="Contraseña de admin" class="w-full border border-gray-300 rounded px-3 py-2 mb-4" />
            <div id="errorMsg" class="text-red-600 text-sm mb-3 hidden"></div>
            <div class="flex gap-3">
                <button class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-2 rounded btnCancel">
                    Cancelar
                </button>
                <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded btnConfirm">
                    Aceptar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    const passwordInput = modal.querySelector('#adminPassword');
    const btnCancel = modal.querySelector('.btnCancel');
    const btnConfirm = modal.querySelector('.btnConfirm');
    const errorMsg = modal.querySelector('#errorMsg');
    
    btnCancel.addEventListener('click', () => {
        modal.remove();
    });
    
    btnConfirm.addEventListener('click', () => {
        const password = passwordInput.value;
        if (!password) {
            errorMsg.textContent = 'Por favor ingresa la contraseña';
            errorMsg.classList.remove('hidden');
            return;
        }
        agregarConAdmin(field, valor, password, modal, tomSelect);
    });
    
    passwordInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') btnConfirm.click();
    });
    
    passwordInput.focus();
}

// Modal de login admin para eliminar
function solicitarLoginEliminar(field, valor) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 mb-4">🔐 Acceso Administrador</h3>
            <p class="text-gray-600 mb-4 text-sm">
                Ingresa contraseña de administrador para eliminar <strong>"${valor}"</strong>
            </p>
            <input type="password" id="adminPassword2" placeholder="Contraseña de admin" class="w-full border border-gray-300 rounded px-3 py-2 mb-4" />
            <div class="flex gap-3">
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-2 rounded">
                    Cancelar
                </button>
                <button onclick="eliminarConAdmin('${field}', '${valor}', document.getElementById('adminPassword2').value)" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded">
                    Aceptar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('adminPassword2').focus();
}

// Agregar solicitante (sin auth) - FINAL
function agregarSolicitanteFinal(field, valor, modal, tomSelect) {
    fetch('add_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor, password: null })
    })
    .then(res => res.json())
    .then(data => {
        modal.remove();
        if (data.success) {
            // Agregar a Tom-Select
            tomSelect.addOption({ value: valor, text: valor });
            tomSelect.setValue(valor);
            mostrarNotificacion(`"${valor}" agregado a solicitantes ✓`, 'green');
        } else {
            mostrarNotificacion(data.message || 'Error al agregar', 'red');
        }
    })
    .catch(err => {
        modal.remove();
        mostrarNotificacion('Error de conexión: ' + err.message, 'red');
        console.error(err);
    });
}

// Agregar con admin
function agregarConAdmin(field, valor, password, modal, tomSelect) {
    fetch('add_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor, password })
    })
    .then(res => res.json())
    .then(data => {
        modal.remove();
        if (data.success) {
            // Agregar a Tom-Select
            tomSelect.addOption({ value: valor, text: valor });
            tomSelect.setValue(valor);
            mostrarNotificacion(`"${valor}" agregado a ${field} ✓`, 'green');
        } else if (data.message && (data.message.includes('credenciales') || data.message.includes('inválida'))) {
            mostrarNotificacion('Contraseña incorrecta ✗', 'red');
        } else {
            mostrarNotificacion(data.message || 'Error al agregar', 'red');
        }
    })
    .catch(err => {
        modal.remove();
        mostrarNotificacion('Error de conexión: ' + err.message, 'red');
        console.error(err);
    });
}

// Eliminar con admin
function eliminarConAdmin(field, valor, password) {
    fetch('remove_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor, password })
    })
    .then(res => res.json())
    .then(data => {
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) modal.remove();
        
        if (data.success) {
            mostrarNotificacion(`"${valor}" eliminado de ${field} ✓`, 'green');
            setTimeout(() => location.reload(), 1500);
        } else if (data.message && (data.message.includes('credenciales') || data.message.includes('inválida'))) {
            mostrarNotificacion('Contraseña incorrecta ✗', 'red');
        } else {
            mostrarNotificacion(data.message || 'Error al eliminar', 'red');
        }
    })
    .catch(err => {
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) modal.remove();
        mostrarNotificacion('Error de conexión: ' + err.message, 'red');
        console.error(err);
    });
}

// Notificación flotante mejorada
function mostrarNotificacion(mensaje, tipo = 'info') {
    const notif = document.createElement('div');
    const estilos = {
        'green': 'bg-green-100 text-green-800 border border-green-400',
        'red': 'bg-red-100 text-red-800 border border-red-400',
        'blue': 'bg-blue-100 text-blue-800 border border-blue-400'
    };
    notif.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${estilos[tipo] || estilos['blue']} z-40 max-w-sm animate-pulse`;
    notif.innerHTML = `<div class="font-medium">${mensaje}</div>`;
    document.body.appendChild(notif);
    setTimeout(() => {
        notif.style.opacity = '0';
        notif.style.transition = 'opacity 0.5s ease';
        setTimeout(() => notif.remove(), 500);
    }, 3000);
}

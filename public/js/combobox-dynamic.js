/**
 * combobox-dynamic.js — Gestión dinámica de valores en combobox
 * Solicitante: cualquiera puede agregar (sin admin)
 * Otros campos (requerimiento, negocio, etc): solo el admin (ignacio.riquelme@cliptecnologia.com)
 * Admin NO requiere contraseña adicional - solo confirmación en modal
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

// Manejo de nuevo solicitante (sin admin - cualquiera puede agregar)
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
                    Cancelar
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

// Manejo de nuevo valor con admin (sin contraseña - solo confirm)
function handleNuevoValorAdmin(field, valor, tomSelect, inputElement) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-blue-700 mb-3">🔐 Confirmar Acción de Administrador</h3>
            <p class="text-gray-700 mb-6">
                ¿Agregar <strong>"${valor}"</strong> al campo <strong>${field}</strong>?
            </p>
            <div class="flex gap-3">
                <button class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold py-2 px-4 rounded transition btnNo">
                    Cancelar
                </button>
                <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition btnSi">
                    Aceptar
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
        agregarConAdmin(field, valor, tomSelect);
    });
}

// Agregar solicitante (sin admin - cualquiera) - FINAL
function agregarSolicitanteFinal(field, valor, modal, tomSelect) {
    fetch('add_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor })
    })
    .then(res => res.json())
    .then(data => {
        modal.remove();
        if (data.success) {
            mostrarNotificacion(data.message, 'green');
            // Recargar página después de 1.5 segundos para que se vea el cambio en el listado
            setTimeout(() => location.reload(), 1500);
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

// Agregar con admin (sin contraseña - usa sesión actual)
function agregarConAdmin(field, valor, tomSelect) {
    fetch('add_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion(data.message, 'green');
            // Recargar página después de 1.5 segundos para que se vea el cambio en el listado
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarNotificacion(data.message || 'Error al agregar', 'red');
        }
    })
    .catch(err => {
        mostrarNotificacion('Error de conexión: ' + err.message, 'red');
        console.error(err);
    });
}

// Eliminar con admin (sin contraseña - usa sesión actual)
function eliminarConAdmin(field, valor) {
    fetch('remove_combobox_value.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value: valor })
    })
    .then(res => res.json())
    .then(data => {
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) modal.remove();
        
        if (data.success) {
            mostrarNotificacion(data.message, 'green');
            setTimeout(() => location.reload(), 1500);
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

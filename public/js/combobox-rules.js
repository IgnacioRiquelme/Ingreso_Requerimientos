/**
 * combobox-rules.js — Sistema de combobox dinámicos y dependientes
 * Detecta cambios en campos padre y preselecciona campos hijo
 */

let comboboxRules = [];
let comboboxDefaults = {};
let tomSelectInstances = {};

// Campos padre que disparan cambios
const PARENT_FIELDS = ['requerimiento', 'negocio', 'ambiente'];
// Campos hijo que se preseleccionan
const CHILD_FIELDS = ['capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'];

/**
 * Inicializar el sistema de reglas dinámicas
 */
function initializeComboboxRules() {
    console.log('🔄 Inicializando sistema de reglas dinámicas...');
    // Cargar reglas desde API
    fetch('get_combobox_rules.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                comboboxRules = data.rules;
                comboboxDefaults = data.defaults;
                
                console.log('✓ Reglas de combobox cargadas:', comboboxRules.length, 'reglas');
                console.log('📋 Reglas:', comboboxRules);
                console.log('⚙️ Valores por defecto:', comboboxDefaults);
                
                // Configurar listeners en campos padre
                setupParentFieldListeners();
                
                // Aplicar reglas por defecto al cargar
                applyComboboxRules();
            } else {
                console.error('Error cargando reglas:', data.message);
            }
        })
        .catch(err => {
            console.error('Error al obtener reglas:', err);
        });
}

/**
 * Configurar listeners en campos padre
 */
function setupParentFieldListeners() {
    console.log('🔗 Configurando listeners en campos padre...');
    PARENT_FIELDS.forEach(fieldName => {
        const select = document.querySelector(`select[name="${fieldName}"]`);
        console.log(`  - Campo ${fieldName}: select=${!!select}, tomSelectInstance=${!!tomSelectInstances[fieldName]}`);
        
        if (select && tomSelectInstances[fieldName]) {
            // Escuchar cambios en Tom-Select
            tomSelectInstances[fieldName].on('change', () => {
                console.log(`📍 Cambio detectado en ${fieldName}:`, tomSelectInstances[fieldName].getValue());
                applyComboboxRules();
            });
        }
    });
}

/**
 * Obtener valores actuales de campos padre
 */
function getParentFieldValues() {
    const requerimiento = (getFieldValue('requerimiento') || '').trim();
    const negocio = (getFieldValue('negocio') || '').trim();
    const ambiente = (getFieldValue('ambiente') || '').trim();
    
    return { requerimiento, negocio, ambiente };
}

/**
 * Obtener valor actual de un campo
 */
function getFieldValue(fieldName) {
    const ts = tomSelectInstances[fieldName];
    if (ts) {
        const value = ts.getValue();
        const result = Array.isArray(value) ? value[0] : value;
        console.log(`   📌 ${fieldName}: "${result}"`);
        return result;
    }
    console.log(`   ⚠ ${fieldName}: NO TIENE INSTANCIA`);
    return null;
}

/**
 * Aplicar reglas de combobox dinámicos
 */
function applyComboboxRules() {
    const parentValues = getParentFieldValues();
    console.log('🔍 Buscando regla para:', parentValues);
    console.log('📋 Reglas disponibles en JS:', comboboxRules);
    
    // Buscar regla correspondiente con comparación case-insensitive + trim
    // Cada regla puede tener match_fields explícito (ej. solo req+negocio) o usa PARENT_FIELDS por defecto
    const matchedRule = comboboxRules.find(rule => {
        const matchFields = rule.match_fields || PARENT_FIELDS;
        
        const matches = matchFields.every(field => {
            const ruleVal = (rule[field] || '').trim().toLowerCase();
            const currentVal = (getFieldValue(field) || '').trim().toLowerCase();
            console.log(`  Comparando [${field}]: "${ruleVal}" vs "${currentVal}"`);
            return ruleVal === currentVal;
        });
        
        console.log(`  Resultado regla "${rule.requerimiento}" + "${rule.negocio}": ${matches}`);
        return matches;
    });
    
    // Aplicar valores
    if (matchedRule) {
        // Registra cuando encuentra regla exacta
        console.log('✅ REGLA EXACTA encontrada:', matchedRule);
        applyFieldValues(matchedRule);
    } else {
        // Aplicar solo valores por defecto
        console.log('❌ Regla exacta NO encontrada. Aplicando valores por defecto:', comboboxDefaults);
        applyDefaultValues();
    }
}

/**
 * Aplicar valores específicos de una regla
 * Si la regla tiene match_fields, también pre-rellena campos padre no usados en el match (ej. ambiente)
 */
function applyFieldValues(rule) {
    const matchFields = rule.match_fields || PARENT_FIELDS;
    // Campos a pre-rellenar: hijos siempre + padres que NO son parte del match (ej. ambiente en reglas de 2 padres)
    const extraParents = PARENT_FIELDS.filter(f => !matchFields.includes(f));
    const fieldsToFill = [...CHILD_FIELDS, ...extraParents];
    
    fieldsToFill.forEach(fieldName => {
        const value = rule[fieldName];
        if (value && tomSelectInstances[fieldName]) {
            setFieldValue(fieldName, value);
        }
    });
}

/**
 * Aplicar solo valores por defecto
 */
function applyDefaultValues() {
    Object.keys(comboboxDefaults).forEach(fieldName => {
        const value = comboboxDefaults[fieldName];
        if (tomSelectInstances[fieldName]) {
            setFieldValue(fieldName, value);
        }
    });
}

/**
 * Establecer valor de un campo Tom-Select
 */
function setFieldValue(fieldName, value) {
    const ts = tomSelectInstances[fieldName];
    const cleanValue = (value || '').trim().toLowerCase();
    
    if (ts) {
        // Verificar si el valor existe en las opciones (case-insensitive)
        const options = ts.options;
        const exists = Object.values(options).some(opt => (opt.value || '').trim().toLowerCase() === cleanValue);
        
        if (exists) {
            // Encontrar la opción exacta (case-insensitive + trim)
            const exactOption = Object.values(options).find(opt => (opt.value || '').trim().toLowerCase() === cleanValue);
            if (exactOption) {
                ts.setValue(exactOption.value);
                console.log(`    ✅ ${fieldName}: "${value.trim()}" establecido`);
            }
        } else {
            console.log(`    ⚠️ ${fieldName}: "${value.trim()}" NO EXISTE (valores disponibles: ${Object.values(options).map(o => o.value).join(', ')})`);
        }
    } else {
        console.log(`    ⚠️ ${fieldName}: NO TIENE INSTANCIA`);
    }
}

/**
 * Registrar instancia de Tom-Select (llamado desde submit.php)
 */
function registerTomSelectInstance(fieldName, tomSelectInstance) {
    tomSelectInstances[fieldName] = tomSelectInstance;
    console.log(`✏️ Registrada instancia Tom-Select para: ${fieldName}`);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM loading - Esperando 500ms para registrar instancias...');
    setTimeout(() => {
        console.log('⏱️ Iniciando combobox-rules.js');
        console.log('Instancias registradas:', Object.keys(tomSelectInstances));
        initializeComboboxRules();
    }, 500);
});

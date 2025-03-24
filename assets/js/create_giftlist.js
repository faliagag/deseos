/**
 * GiftList Manager - Script para gestionar la creación de listas de regalos
 * Este script maneja la lógica para:
 * 1. Alternar entre listas personalizadas y predeterminadas
 * 2. Cargar productos predeterminados vía AJAX
 * 3. Calcular totales para cada producto y total general
 * 4. Agregar/eliminar productos
 */

document.addEventListener('DOMContentLoaded', function() {
    // Referencias a elementos DOM
    const listTypeSelect = document.getElementById('list_type');
    const presetSection = document.getElementById('predeterminada_section');
    const customSection = document.getElementById('personalizada_section');
    const presetThemeSelect = document.getElementById('preset_theme');
    const presetProductsContainer = document.getElementById('preset_products_container');
    const customProductsContainer = document.getElementById('custom_products_container');
    const addCustomProductBtn = document.getElementById('add_custom_product');
    const grandTotalElement = document.getElementById('grand_total');
    const eventTypeSelect = document.querySelector('select[name="event_type"]');
    
    // Inicialización
    initApp();
    
    /**
     * Inicializa la aplicación y configura los event listeners
     */
    function initApp() {
        // Event listeners para cambios de tipo de lista
        listTypeSelect.addEventListener('change', handleListTypeChange);
        
        // Event listener para cambios de tipo de evento (para beneficiarios)
        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', handleEventTypeChange);
        }
        
        // Event listener para selección de tema predeterminado
        if (presetThemeSelect) {
            presetThemeSelect.addEventListener('change', function() {
                loadPresetProducts(this.value);
            });
        }
        
        // Event listener para agregar producto personalizado
        if (addCustomProductBtn) {
            addCustomProductBtn.addEventListener('click', addCustomProduct);
        }
        
        // Event listeners para eliminar productos
        if (customProductsContainer) {
            customProductsContainer.addEventListener('click', function(e) {
                if (e.target && e.target.matches('.remove-custom-product')) {
                    removeCustomProduct(e);
                }
            });
        }
        
        if (presetProductsContainer) {
            presetProductsContainer.addEventListener('click', function(e) {
                if (e.target && e.target.matches('.remove-product')) {
                    removePresetProduct(e);
                }
            });
        }
        
        // Event listener global para cambios en inputs de precio o cantidad
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="price_custom[]"], input[name="quantity_custom[]"]')) {
                calculateTotalsCustom();
                calculateGrandTotal();
            }
            if (e.target.matches('input[name="price[]"], input[name="quantity[]"]')) {
                calculateTotalsPreset();
                calculateGrandTotal();
            }
        });
        
        // Inicializar el estado actual
        handleListTypeChange();
    }
    
    /**
     * Maneja el cambio entre tipos de lista (predeterminada/personalizada)
     */
    function handleListTypeChange() {
        const listType = listTypeSelect.value;
        
        if (listType === 'predeterminada') {
            presetSection.classList.remove('d-none');
            customSection.classList.add('d-none');
        } else {
            presetSection.classList.add('d-none');
            customSection.classList.remove('d-none');
        }
        
        calculateGrandTotal();
    }
    
    /**
     * Maneja el cambio de tipo de evento (para mostrar beneficiarios)
     */
    function handleEventTypeChange() {
        const eventType = eventTypeSelect.value;
        const beneficiarySingle = document.getElementById('beneficiarySingle');
        const beneficiaryDouble = document.getElementById('beneficiaryDouble');
        
        if (eventType === "Matrimonio") {
            beneficiarySingle.classList.add('d-none');
            beneficiaryDouble.classList.remove('d-none');
        } else {
            beneficiarySingle.classList.remove('d-none');
            beneficiaryDouble.classList.add('d-none');
        }
    }
    
    /**
     * Carga productos predeterminados para un tema específico
     * @param {number} presetId - ID del preset seleccionado
     */
    function loadPresetProducts(presetId) {
        if (!presetId) {
            presetProductsContainer.innerHTML = "";
            return;
        }
        
        // Mostrar indicador de carga
        presetProductsContainer.innerHTML = '<div class="alert alert-info">Cargando productos...</div>';
        
        // Realizar solicitud AJAX para obtener productos
        fetch('admin/get_preset_products.php?preset_id=' + encodeURIComponent(presetId))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                presetProductsContainer.innerHTML = ""; // Limpiar contenedor
                
                if (data.success && data.products && data.products.length > 0) {
                    data.products.forEach(function(prod) {
                        const productElement = createPresetProductElement(prod);
                        presetProductsContainer.appendChild(productElement);
                    });
                    calculateTotalsPreset();
                    calculateGrandTotal();
                } else {
                    presetProductsContainer.innerHTML = "<div class='alert alert-warning'>No se encontraron productos para este temario.</div>";
                }
            })
            .catch(error => {
                console.error("Error al cargar productos:", error);
                presetProductsContainer.innerHTML = 
                    "<div class='alert alert-danger'>Error al cargar los productos predeterminados. " + 
                    "Detalles: " + error.message + "</div>";
            });
    }
    
    /**
     * Crea un elemento DOM para un producto predeterminado
     * @param {Object} product - Datos del producto
     * @returns {HTMLElement} Elemento DOM del producto
     */
    function createPresetProductElement(product) {
        const div = document.createElement('div');
        div.className = "product-group border p-3 mb-3 rounded";
        div.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Producto:</label>
                    <input type="hidden" name="product_id[]" value="${product.id}">
                    <input type="text" class="form-control" value="${product.name}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio:</label>
                    <input type="number" name="price[]" class="form-control" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cantidad:</label>
                    <input type="number" name="quantity[]" class="form-control" min="0" value="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Total:</label>
                    <div class="form-control bg-light total-field">0.00</div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-danger mt-2 remove-product">Eliminar</button>
        `;
        return div;
    }
    
    /**
     * Agrega un nuevo producto personalizado
     */
    function addCustomProduct() {
        const firstGroup = customProductsContainer.querySelector('.product-custom-group');
        if (!firstGroup) return;
        
        const newGroup = firstGroup.cloneNode(true);
        
        // Limpiar valores
        newGroup.querySelectorAll('input').forEach(function(input) {
            input.value = "";
        });
        
        // Reiniciar total
        newGroup.querySelector('.total-field-custom').textContent = "0.00";
        
        // Añadir al contenedor
        customProductsContainer.appendChild(newGroup);
    }
    
    /**
     * Elimina un producto personalizado
     * @param {Event} e - Evento de clic
     */
    function removeCustomProduct(e) {
        const groups = document.querySelectorAll('.product-custom-group');
        if (groups.length > 1) {
            e.target.closest('.product-custom-group').remove();
            calculateTotalsCustom();
            calculateGrandTotal();
        } else {
            alert("Debe haber al menos un producto.");
        }
    }
    
    /**
     * Elimina un producto predeterminado
     * @param {Event} e - Evento de clic
     */
    function removePresetProduct(e) {
        const groups = document.querySelectorAll('.product-group');
        if (groups.length > 1) {
            e.target.closest('.product-group').remove();
            calculateTotalsPreset();
            calculateGrandTotal();
        } else {
            alert("Debe haber al menos un producto.");
        }
    }
    
    /**
     * Calcula totales para productos personalizados
     */
    function calculateTotalsCustom() {
        document.querySelectorAll('.product-custom-group').forEach(function(group) {
            const priceInput = group.querySelector('input[name="price_custom[]"]');
            const quantityInput = group.querySelector('input[name="quantity_custom[]"]');
            const totalField = group.querySelector('.total-field-custom');
            
            if (priceInput && quantityInput && totalField) {
                const price = parseFloat(priceInput.value) || 0;
                const quantity = parseFloat(quantityInput.value) || 0;
                const total = price * quantity;
                totalField.textContent = total.toFixed(2);
            }
        });
    }
    
    /**
     * Calcula totales para productos predeterminados
     */
    function calculateTotalsPreset() {
        document.querySelectorAll('.product-group').forEach(function(group) {
            const priceInput = group.querySelector('input[name="price[]"]');
            const quantityInput = group.querySelector('input[name="quantity[]"]');
            const totalField = group.querySelector('.total-field');
            
            if (priceInput && quantityInput && totalField) {
                const price = parseFloat(priceInput.value) || 0;
                const quantity = parseFloat(quantityInput.value) || 0;
                const total = price * quantity;
                totalField.textContent = total.toFixed(2);
            }
        });
    }
    
    /**
     * Calcula el total general según el tipo de lista activa
     */
    function calculateGrandTotal() {
        let grandTotal = 0;
        const listType = listTypeSelect.value;
        
        if (listType === 'predeterminada') {
            document.querySelectorAll('.product-group .total-field').forEach(function(field) {
                grandTotal += parseFloat(field.textContent) || 0;
            });
        } else {
            document.querySelectorAll('.product-custom-group .total-field-custom').forEach(function(field) {
                grandTotal += parseFloat(field.textContent) || 0;
            });
        }
        
        if (grandTotalElement) {
            grandTotalElement.textContent = grandTotal.toFixed(2);
        }
    }
});
/**
 * SCRIPT PRINCIPAL MEJORADO - VERSIÓN 2.1
 * Compatible con todos los navegadores modernos
 * Funcionalidades actualizadas según milistaderegalos.cl
 */

// Compatibilidad con navegadores antiguos
if (!window.fetch) {
    console.warn('Fetch API no disponible, usando XMLHttpRequest como fallback');
}

// Variables globales
var GiftListApp = {
    config: {
        searchDelay: 300,
        maxResults: 50,
        currency: 'CLP'
    },
    cache: new Map(),
    searchTimeout: null
};

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('GiftList App v2.1 - Inicializando...');
    
    // Inicializar componentes Bootstrap
    initializeBootstrapComponents();
    
    // Inicializar validación de formularios
    initializeFormValidation();
    
    // Inicializar funciones de copia
    initializeCopyFunctions();
    
    // Inicializar calculadoras de precios
    initializePriceCalculators();
    
    // Inicializar búsqueda en tiempo real
    initializeSearchFunctionality();
    
    // Inicializar smooth scroll para navegación
    initializeSmoothScroll();
    
    console.log('GiftList App inicializada correctamente');
});

/**
 * Inicializar componentes de Bootstrap con compatibilidad
 */
function initializeBootstrapComponents() {
    try {
        // Inicializar tooltips si Bootstrap está disponible
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Inicializar popovers
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }
        
        console.log('Componentes Bootstrap inicializados');
    } catch (error) {
        console.error('Error inicializando Bootstrap:', error);
    }
}

/**
 * Inicializar validación de formularios HTML5
 */
function initializeFormValidation() {
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    console.log('Validación de formularios inicializada');
}

/**
 * Inicializar funciones de copia al portapapeles
 */
function initializeCopyFunctions() {
    var copyButtons = document.querySelectorAll('.btn-copy');
    
    copyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var targetSelector = button.getAttribute('data-target');
            var target = document.querySelector(targetSelector);
            
            if (target) {
                // Intentar usar la API moderna del portapapeles
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(target.value || target.textContent).then(function() {
                        showCopyFeedback(button);
                    }).catch(function(error) {
                        // Fallback al método clásico
                        copyToClipboardFallback(target, button);
                    });
                } else {
                    // Fallback para navegadores antiguos
                    copyToClipboardFallback(target, button);
                }
            }
        });
    });
}

/**
 * Método fallback para copiar al portapapeles
 */
function copyToClipboardFallback(target, button) {
    try {
        target.select();
        target.setSelectionRange(0, 99999); // Para dispositivos móviles
        document.execCommand('copy');
        showCopyFeedback(button);
    } catch (error) {
        console.error('Error copiando al portapapeles:', error);
        alert('No se pudo copiar al portapapeles. Cópialo manualmente.');
    }
}

/**
 * Mostrar feedback de copia exitosa
 */
function showCopyFeedback(button) {
    var originalText = button.textContent;
    var originalClasses = button.className;
    
    button.textContent = '¡Copiado!';
    button.classList.remove('btn-secondary');
    button.classList.add('btn-success');
    
    setTimeout(function() {
        button.textContent = originalText;
        button.className = originalClasses;
    }, 2000);
}

/**
 * Inicializar calculadoras de precios
 */
function initializePriceCalculators() {
    // Listeners para inputs de precio y cantidad
    document.addEventListener('input', function(event) {
        var target = event.target;
        
        if (target.matches('input[name="price_custom[]"], input[name="quantity_custom[]"]')) {
            calculateTotalsCustom();
            calculateGrandTotal();
        }
        
        if (target.matches('input[name="price[]"], input[name="quantity[]"]')) {
            calculateTotalsPreset();
            calculateGrandTotal();
        }
    });
    
    // Cálculo inicial
    calculateTotalsCustom();
    calculateTotalsPreset();
    calculateGrandTotal();
}

/**
 * Calcular totales para productos personalizados
 */
function calculateTotalsCustom() {
    var groups = document.querySelectorAll('.product-custom-group');
    
    groups.forEach(function(group) {
        var priceInput = group.querySelector('input[name="price_custom[]"]');
        var quantityInput = group.querySelector('input[name="quantity_custom[]"]');
        var totalField = group.querySelector('.total-field-custom');
        
        if (priceInput && quantityInput && totalField) {
            var price = parseFloat(priceInput.value) || 0;
            var quantity = parseFloat(quantityInput.value) || 0;
            var total = price * quantity;
            totalField.textContent = formatMoneyCLP(total);
        }
    });
}

/**
 * Calcular totales para productos predeterminados
 */
function calculateTotalsPreset() {
    var groups = document.querySelectorAll('.product-group');
    
    groups.forEach(function(group) {
        var priceInput = group.querySelector('input[name="price[]"]');
        var quantityInput = group.querySelector('input[name="quantity[]"]');
        var totalField = group.querySelector('.total-field');
        
        if (priceInput && quantityInput && totalField) {
            var price = parseFloat(priceInput.value) || 0;
            var quantity = parseFloat(quantityInput.value) || 0;
            var total = price * quantity;
            totalField.textContent = formatMoneyCLP(total);
        }
    });
}

/**
 * Calcular total general
 */
function calculateGrandTotal() {
    var grandTotalElement = document.getElementById('grand-total');
    
    if (grandTotalElement) {
        var grandTotal = 0;
        
        // Sumar totales de productos personalizados
        document.querySelectorAll('.total-field-custom').forEach(function(el) {
            var value = el.textContent.replace(/[$\.]/g, '');
            grandTotal += parseInt(value) || 0;
        });
        
        // Sumar totales de productos predeterminados
        document.querySelectorAll('.total-field').forEach(function(el) {
            var value = el.textContent.replace(/[$\.]/g, '');
            grandTotal += parseInt(value) || 0;
        });
        
        grandTotalElement.textContent = formatMoneyCLP(grandTotal);
    }
}

/**
 * Formatear montos en pesos chilenos (compatible con todos los navegadores)
 */
function formatMoneyCLP(amount) {
    var roundedAmount = Math.round(amount);
    
    // Usar Intl.NumberFormat si está disponible (navegadores modernos)
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        try {
            return '$' + new Intl.NumberFormat('es-CL', {
                maximumFractionDigits: 0,
                minimumFractionDigits: 0
            }).format(roundedAmount);
        } catch (error) {
            // Fallback si Intl no funciona correctamente
            return '$' + addThousandsSeparator(roundedAmount);
        }
    } else {
        // Fallback para navegadores antiguos
        return '$' + addThousandsSeparator(roundedAmount);
    }
}

/**
 * Agregar separadores de miles (fallback)
 */
function addThousandsSeparator(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Inicializar funcionalidad de búsqueda en tiempo real
 */
function initializeSearchFunctionality() {
    var searchInput = document.getElementById('search-input');
    var searchLoader = document.getElementById('search-loader');
    var searchResults = document.getElementById('search-results');
    var resultsHeader = document.getElementById('results-header');
    var noResultsMessage = document.getElementById('no-results-message');
    var searchFilters = document.getElementById('search-filters');
    var eventFilter = document.getElementById('event-filter');
    var clearFilters = document.getElementById('clear-filters');
    
    if (!searchInput) return;
    
    var currentSearchTerm = searchInput.value;
    
    // Mostrar filtros cuando se empiece a escribir
    searchInput.addEventListener('focus', function() {
        if (this.value.length > 0 && searchFilters) {
            searchFilters.style.display = 'block';
        }
    });
    
    // Búsqueda en tiempo real
    searchInput.addEventListener('input', function() {
        var searchTerm = this.value.trim();
        
        if (searchTerm.length > 0 && searchFilters) {
            searchFilters.style.display = 'block';
        } else if (searchFilters) {
            searchFilters.style.display = 'none';
        }
        
        if (GiftListApp.searchTimeout) {
            clearTimeout(GiftListApp.searchTimeout);
        }
        
        if (searchTerm === currentSearchTerm) {
            return;
        }
        
        if (!searchTerm) {
            if (currentSearchTerm) {
                window.location.href = 'index.php';
            }
            return;
        }
        
        GiftListApp.searchTimeout = setTimeout(function() {
            currentSearchTerm = searchTerm;
            performSearch(searchTerm);
        }, GiftListApp.config.searchDelay);
    });
    
    // Filtro por evento
    if (eventFilter) {
        eventFilter.addEventListener('change', function() {
            var searchTerm = searchInput.value.trim();
            if (searchTerm) {
                performSearch(searchTerm, this.value);
            }
        });
    }
    
    // Limpiar filtros
    if (clearFilters) {
        clearFilters.addEventListener('click', function() {
            searchInput.value = '';
            eventFilter.value = '';
            window.location.href = 'index.php';
        });
    }
    
    /**
     * Realizar búsqueda AJAX
     */
    function performSearch(searchTerm, eventType) {
        eventType = eventType || '';
        
        if (searchLoader) {
            searchLoader.style.display = 'inline-block';
        }
        
        if (searchResults) {
            searchResults.style.opacity = '0.5';
        }
        
        var url = 'index.php?q=' + encodeURIComponent(searchTerm);
        if (eventType) {
            url += '&event=' + encodeURIComponent(eventType);
        }
        url += '&ajax=1';
        
        // Usar fetch si está disponible, sino XMLHttpRequest
        if (window.fetch) {
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                updateResults(data, searchTerm);
            })
            .catch(function(error) {
                console.error('Error en la búsqueda:', error);
                showSearchError();
            })
            .finally(function() {
                if (searchLoader) {
                    searchLoader.style.display = 'none';
                }
                if (searchResults) {
                    searchResults.style.opacity = '1';
                }
            });
        } else {
            // Fallback con XMLHttpRequest
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (searchLoader) {
                    searchLoader.style.display = 'none';
                }
                if (searchResults) {
                    searchResults.style.opacity = '1';
                }
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        updateResults(response, searchTerm);
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        showSearchError();
                    }
                } else {
                    showSearchError();
                }
            };
            
            xhr.onerror = function() {
                if (searchLoader) {
                    searchLoader.style.display = 'none';
                }
                if (searchResults) {
                    searchResults.style.opacity = '1';
                }
                showSearchError();
            };
            
            xhr.send();
        }
    }
    
    /**
     * Actualizar resultados de búsqueda
     */
    function updateResults(response, searchTerm) {
        if (resultsHeader) {
            resultsHeader.innerHTML = '<h2 class="mb-4">Resultados para "' + escapeHtml(searchTerm) + '"</h2>';
        }
        
        if (response.count > 0) {
            if (noResultsMessage) {
                noResultsMessage.style.display = 'none';
            }
            
            var tableHTML = buildResultsTable(response.results, searchTerm);
            
            if (searchResults) {
                searchResults.innerHTML = tableHTML;
            }
        } else {
            if (searchResults) {
                searchResults.innerHTML = '';
            }
            if (noResultsMessage) {
                noResultsMessage.style.display = 'block';
            }
        }
    }
    
    /**
     * Construir tabla de resultados
     */
    function buildResultsTable(results, searchTerm) {
        var tableHTML = '<div class="table-responsive">' +
            '<table class="table table-hover">' +
            '<thead class="table-primary">' +
            '<tr>' +
            '<th>Lista</th>' +
            '<th>Evento</th>' +
            '<th>Beneficiario(s)</th>' +
            '<th>Creador</th>' +
            '<th>Regalos</th>' +
            '<th>Fecha</th>' +
            '<th>Acciones</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';
        
        results.forEach(function(list) {
            var highlightedTitle = highlightSearchTerm(list.title, searchTerm);
            var description = list.description ? 
                '<br><small class="text-muted">' + 
                escapeHtml(list.description.substring(0, 60)) + 
                (list.description.length > 60 ? '...' : '') + 
                '</small>' : '';
            
            tableHTML += '<tr>' +
                '<td><strong>' + highlightedTitle + '</strong>' + description + '</td>' +
                '<td><span class="badge bg-info">' + escapeHtml(list.event_type) + '</span></td>' +
                '<td>' + escapeHtml(list.beneficiaries) + '</td>' +
                '<td>' + escapeHtml(list.creator_name) + '</td>' +
                '<td><span class="badge bg-info">' + (list.gift_count || 0) + ' regalos</span></td>' +
                '<td>' + escapeHtml(list.formatted_date) + '</td>' +
                '<td>' +
                '<a href="giftlist.php?link=' + encodeURIComponent(list.unique_link) + '" ' +
                'class="btn btn-primary btn-sm">' +
                '<i class="bi bi-eye"></i> Ver Lista' +
                '</a>' +
                '</td>' +
                '</tr>';
        });
        
        tableHTML += '</tbody></table></div>';
        
        return tableHTML;
    }
    
    /**
     * Resaltar término de búsqueda
     */
    function highlightSearchTerm(text, searchTerm) {
        if (!searchTerm || !text) return escapeHtml(text);
        
        var escapedText = escapeHtml(text);
        var escapedSearchTerm = escapeHtml(searchTerm);
        var regex = new RegExp('(' + escapedSearchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        
        return escapedText.replace(regex, '<mark>$1</mark>');
    }
    
    /**
     * Mostrar error de búsqueda
     */
    function showSearchError() {
        if (searchResults) {
            searchResults.innerHTML = 
                '<div class="alert alert-warning">' +
                '<i class="bi bi-exclamation-triangle"></i> ' +
                'Error al realizar la búsqueda. Inténtalo nuevamente.' +
                '</div>';
        }
    }
}

/**
 * Inicializar scroll suave para navegación
 */
function initializeSmoothScroll() {
    var links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(function(link) {
        link.addEventListener('click', function(event) {
            var targetId = this.getAttribute('href');
            var target = document.querySelector(targetId);
            
            if (target) {
                event.preventDefault();
                
                // Usar scroll suave si está disponible
                if ('scrollBehavior' in document.documentElement.style) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                } else {
                    // Fallback para navegadores antiguos
                    target.scrollIntoView();
                }
            }
        });
    });
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { 
        return map[m]; 
    });
}

/**
 * Funciones de utilidad global
 */
window.GiftListApp = GiftListApp;
window.formatMoneyCLP = formatMoneyCLP;
window.calculateGrandTotal = calculateGrandTotal;
window.calculateTotalsCustom = calculateTotalsCustom;
window.calculateTotalsPreset = calculateTotalsPreset;
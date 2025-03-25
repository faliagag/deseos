// assets/js/script.js

// Common functions
document.addEventListener("DOMContentLoaded", function(){
    console.log("GiftList App initialized");
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Copy to clipboard functionality
    const copyButtons = document.querySelectorAll('.btn-copy');
    copyButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.getAttribute('data-target'));
            target.select();
            document.execCommand('copy');
            
            // Show copied feedback
            const originalText = button.textContent;
            button.textContent = 'Copiado!';
            button.classList.add('btn-success');
            button.classList.remove('btn-secondary');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-secondary');
            }, 2000);
        });
    });
    
    // Calculate gift total prices
    const calculateTotals = () => {
        const priceInputs = document.querySelectorAll('.price-input');
        const quantityInputs = document.querySelectorAll('.quantity-input');
        
        for (let i = 0; i < priceInputs.length; i++) {
            const price = Math.round(parseFloat(priceInputs[i].value) || 0);
            const quantity = parseInt(quantityInputs[i].value) || 0;
            const totalElement = document.querySelector(`#total-${i+1}`);
            
            if (totalElement) {
                totalElement.textContent = formatMoneyCLP(price * quantity);
            }
        }
        
        // Calculate grand total
        const grandTotalElement = document.getElementById('grand-total');
        if (grandTotalElement) {
            let grandTotal = 0;
            document.querySelectorAll('[id^="total-"]').forEach(el => {
                // Extraer el número del texto (quitar $ y puntos)
                const value = el.textContent.replace(/[$\.]/g, '');
                grandTotal += parseInt(value) || 0;
            });
            grandTotalElement.textContent = formatMoneyCLP(grandTotal);
        }
    };
    
    // Add price and quantity change listeners
    const priceInputs = document.querySelectorAll('.price-input');
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    priceInputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    
    quantityInputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    
    // Initial calculation
    calculateTotals();
});

// Función para formatear montos en pesos chilenos (sin decimales)
function formatMoneyCLP(amount) {
    return '$' + new Intl.NumberFormat('es-CL', {
        maximumFractionDigits: 0,
        minimumFractionDigits: 0
    }).format(Math.round(amount));
}
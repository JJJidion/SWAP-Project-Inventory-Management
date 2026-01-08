// ========================================
// MAIN JAVASCRIPT FILE
// ========================================
// Handles client-side interactivity and form validation

// ========================================
// DELETE CONFIRMATION
// ========================================

function confirmDelete(productName) {
    return confirm('Are you sure you want to delete "' + productName + '"?');
}

// ========================================
// AUTO-HIDE ALERTS
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(function(alert) {
        // Wait 3 seconds, then fade out over 0.5 seconds
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';

            // Remove element after fade completes
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 3000);
    });
});

// ========================================
// FORM VALIDATION
// ========================================

function validateProductForm() {
    const name = document.getElementById('name').value.trim();
    const price = document.getElementById('price').value;
    const description = document.getElementById('description').value.trim();

    if (name === '') {
        alert('Please enter a product name');
        return false;
    }

    if (price === '' || price <= 0) {
        alert('Please enter a valid price');
        return false;
    }

    if (description === '') {
        alert('Please enter a description');
        return false;
    }

    return true;
}

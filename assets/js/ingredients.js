// Ingredients Management JavaScript for PortionPro

// Global variables
let allUnits = [];
let unitConversions = {};
let duplicateCheckTimeout;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Show success notification after page reload from save
    try {
        const saved = sessionStorage.getItem('ingredient_saved');
        if (saved === '1') {
            const msg = sessionStorage.getItem('ingredient_saved_msg') || 'Ingredient saved successfully!';
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: msg,
                timer: 1500,
                showConfirmButton: false
            });
            sessionStorage.removeItem('ingredient_saved');
            sessionStorage.removeItem('ingredient_saved_msg');
        }
    } catch (e) { /* ignore */ }

    loadUnits();
    setupEventListeners();
    setupSearchAndFilter();
});

// Load available units
async function loadUnits() {
    try {
        const response = await fetch('api/units.php');
        const data = await response.json();
        allUnits = data.units || [];
        unitConversions = data.conversions || {};
        populateUnitSelects();
    } catch (error) {
        console.error('Error loading units:', error);
    }
}

// Populate unit select options
function populateUnitSelects() {
    const unitSelect = document.getElementById('unit');
    const convertFromUnitSelect = document.getElementById('convertFromUnit');
    const convertToUnitSelect = document.getElementById('convertToUnit');
    
    // Clear existing options
    [unitSelect, convertFromUnitSelect, convertToUnitSelect].forEach(select => {
        select.innerHTML = '<option value="">Select Unit</option>';
    });
    
    // Add units to selects
    allUnits.forEach(unit => {
        const option = `<option value="${unit}">${unit}</option>`;
        unitSelect.innerHTML += option;
        convertFromUnitSelect.innerHTML += option;
        convertToUnitSelect.innerHTML += option;
    });
}

// Setup event listeners
function setupEventListeners() {
    // Form submission
    document.getElementById('ingredientForm').addEventListener('submit', handleFormSubmit);
    
    // Real-time duplicate checking
    document.getElementById('name').addEventListener('input', checkDuplicateName);
    
    // Unit converter
    document.getElementById('convertFrom').addEventListener('input', performUnitConversion);
    document.getElementById('convertFromUnit').addEventListener('change', performUnitConversion);
    document.getElementById('convertToUnit').addEventListener('change', performUnitConversion);
    
    // Show/hide unit converter
    document.getElementById('unit').addEventListener('change', function() {
        const converter = document.getElementById('unitConverter');
        if (this.value) {
            converter.style.display = 'block';
        } else {
            converter.style.display = 'none';
        }
    });
}

// Setup search and filter
function setupSearchAndFilter() {
    const searchInput = document.getElementById('search');
    const categoryFilter = document.getElementById('category_filter');
    
    // Debounced search
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch();
        }, 300);
    });
    
    categoryFilter.addEventListener('change', performSearch);
}

// Perform search and filter
function performSearch() {
    const search = document.getElementById('search').value;
    const category = document.getElementById('category_filter').value;
    
    const url = new URL(window.location);
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    
    if (category) {
        url.searchParams.set('category', category);
    } else {
        url.searchParams.delete('category');
    }
    
    window.location.href = url.toString();
}

// Open add ingredient modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Ingredient';
    document.getElementById('formAction').value = 'add';
    document.getElementById('ingredientForm').reset();
    document.getElementById('unitConverter').style.display = 'none';
    clearDuplicateWarning(); // Clear any existing warnings
    document.getElementById('ingredientModal').style.display = 'block';
}

// Edit ingredient
function editIngredient(ingredient) {
    document.getElementById('modalTitle').textContent = 'Edit Ingredient';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('ingredientId').value = ingredient.id;
    document.getElementById('name').value = ingredient.name;
    document.getElementById('category').value = ingredient.category || '';
    document.getElementById('unit').value = ingredient.unit;
    document.getElementById('price').value = ingredient.price_per_unit;
    
    // Show unit converter
    document.getElementById('unitConverter').style.display = 'block';
    
    // Clear any existing warnings
    clearDuplicateWarning();
    
    document.getElementById('ingredientModal').style.display = 'block';
}

// Show tutorial
function showTutorial() {
    document.getElementById('tutorialModal').style.display = 'block';
}

// Close tutorial
function closeTutorial() {
    document.getElementById('tutorialModal').style.display = 'none';
}

// Show quick tips
function showQuickTips() {
    document.getElementById('quickTipsModal').style.display = 'block';
}

// Close quick tips
function closeQuickTips() {
    document.getElementById('quickTipsModal').style.display = 'none';
}

// Close modal
function closeModal() {
    document.getElementById('ingredientModal').style.display = 'none';
    document.getElementById('ingredientForm').reset();
    clearDuplicateWarning();
}

// Check for duplicate ingredient name
async function checkDuplicateName() {
    const nameInput = document.getElementById('name');
    const name = nameInput.value.trim();
    const excludeId = document.getElementById('ingredientId').value || 0;
    
    // Clear previous timeout
    clearTimeout(duplicateCheckTimeout);
    
    // Don't check if name is empty or too short
    if (name.length < 2) {
        clearDuplicateWarning();
        return;
    }
    
    // Debounce the check
    duplicateCheckTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`api/check_duplicate_ingredient.php?name=${encodeURIComponent(name)}&exclude_id=${excludeId}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.is_duplicate) {
                    showDuplicateWarning(data.message, data.existing_name);
                } else {
                    clearDuplicateWarning();
                }
            }
        } catch (error) {
            console.error('Error checking duplicate:', error);
        }
    }, 500); // 500ms delay
}

// Show duplicate warning
function showDuplicateWarning(message, existingName) {
    const nameInput = document.getElementById('name');
    const formGroup = nameInput.closest('.form-group');
    
    // Remove existing warning
    clearDuplicateWarning();
    
    // Create warning element
    const warning = document.createElement('div');
    warning.className = 'duplicate-warning';
    warning.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        <span>${message}</span>
        <button type="button" onclick="editExistingIngredient('${existingName}')" class="btn btn-sm btn-secondary">
            <i class="fas fa-edit"></i> Edit Existing
        </button>
    `;
    
    // Add warning after the input
    formGroup.appendChild(warning);
    
    // Add error styling to input
    nameInput.classList.add('error');
    
    // Disable form submission
    const submitBtn = document.querySelector('#ingredientForm button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Duplicate Name - Please Change';
}

// Clear duplicate warning
function clearDuplicateWarning() {
    const warning = document.querySelector('.duplicate-warning');
    if (warning) {
        warning.remove();
    }
    
    const nameInput = document.getElementById('name');
    nameInput.classList.remove('error');
    
    // Re-enable form submission
    const submitBtn = document.querySelector('#ingredientForm button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Ingredient';
    }
}

// Edit existing ingredient
function editExistingIngredient(existingName) {
    // Find the ingredient in the current page data
    const tableRows = document.querySelectorAll('.table tbody tr');
    for (let row of tableRows) {
        const nameCell = row.querySelector('td:first-child');
        if (nameCell && nameCell.textContent.trim().toLowerCase() === existingName.toLowerCase()) {
            // Extract ingredient data from the row
            const editBtn = row.querySelector('button[onclick*="editIngredient"]');
            if (editBtn) {
                // Extract the ingredient data from the onclick attribute
                const onclickAttr = editBtn.getAttribute('onclick');
                const match = onclickAttr.match(/editIngredient\((.+)\)/);
                if (match) {
                    try {
                        const ingredientData = JSON.parse(match[1]);
                        closeModal();
                        editIngredient(ingredientData);
                        return;
                    } catch (e) {
                        console.error('Error parsing ingredient data:', e);
                    }
                }
            }
        }
    }
    
    // If not found in current view, show message
    Swal.fire({
        icon: 'info',
        title: 'Ingredient Not Visible',
        text: 'The existing ingredient is not currently visible. Please search for it or clear the filters to find it.',
        confirmButtonText: 'OK'
    });
}

// Handle form submission
async function handleFormSubmit(e) {
    e.preventDefault();
    
    // Check if there's a duplicate warning before submitting
    const duplicateWarning = document.querySelector('.duplicate-warning');
    if (duplicateWarning) {
        Swal.fire({
            icon: 'warning',
            title: 'Duplicate Ingredient',
            text: 'Please change the ingredient name or edit the existing ingredient.',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.innerHTML = '<span class="loading"></span> Saving...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('ingredients.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            // Persist a success flag to show a notification after reload
            const action = formData.get('action');
            try {
                sessionStorage.setItem('ingredient_saved', '1');
                const fallbackMsg = action === 'edit' ? 'Ingredient updated successfully!' : 'Ingredient added successfully!';
                sessionStorage.setItem('ingredient_saved_msg', result.message || fallbackMsg);
            } catch (e) { /* ignore */ }
            location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.message || 'Failed to save ingredient'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving the ingredient'
        });
    } finally {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Delete ingredient
async function deleteIngredient(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Yes, delete it!'
    });
    
    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            const response = await fetch('ingredients.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while deleting the ingredient'
            });
        }
    }
}

// Perform unit conversion
function performUnitConversion() {
    const fromValue = parseFloat(document.getElementById('convertFrom').value);
    const fromUnit = document.getElementById('convertFromUnit').value;
    const toUnit = document.getElementById('convertToUnit').value;
    
    if (!fromValue || !fromUnit || !toUnit) {
        document.getElementById('convertResult').value = '';
        return;
    }
    
    // Check if we have a direct conversion
    const conversionKey = `${fromUnit}_to_${toUnit}`;
    if (unitConversions[conversionKey]) {
        const result = fromValue * unitConversions[conversionKey];
        document.getElementById('convertResult').value = result.toFixed(4);
        return;
    }
    
    // Try reverse conversion
    const reverseKey = `${toUnit}_to_${fromUnit}`;
    if (unitConversions[reverseKey]) {
        const result = fromValue / unitConversions[reverseKey];
        document.getElementById('convertResult').value = result.toFixed(4);
        return;
    }
    
    // No conversion found
    document.getElementById('convertResult').value = 'No conversion available';
}

// Logout function
function logout() {
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Yes, logout!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'api/logout.php';
        }
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const ingredientModal = document.getElementById('ingredientModal');
    const tutorialModal = document.getElementById('tutorialModal');
    const quickTipsModal = document.getElementById('quickTipsModal');
    
    if (event.target === ingredientModal) {
        closeModal();
    }
    if (event.target === tutorialModal) {
        closeTutorial();
    }
    if (event.target === quickTipsModal) {
        closeQuickTips();
    }
}

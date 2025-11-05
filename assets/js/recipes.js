let ingredientRowCount = 0;
let originalServingsForEdit = 1;
let baseQuantityPerRowIndex = {};

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupSearch();
});

function setupEventListeners() {
    document.getElementById('recipeForm').addEventListener('submit', handleFormSubmit);
    
    document.getElementById('servings').addEventListener('input', scaleQuantitiesOnServingsChange);
    document.getElementById('profit_margin').addEventListener('input', calculateCosts);
}

function setupSearch() {
    const searchInput = document.getElementById('search');
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch();
        }, 300);
    });
}

function performSearch() {
    const search = document.getElementById('search').value;
    const url = new URL(window.location);
    
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    
    window.location.href = url.toString();
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Recipe';
    document.getElementById('formAction').value = 'add';
    document.getElementById('recipeForm').reset();
    document.getElementById('ingredientsList').innerHTML = '';
    ingredientRowCount = 0;
    baseQuantityPerRowIndex = {};
    originalServingsForEdit = parseFloat(document.getElementById('servings').value) || 1;
    addIngredientRow();
    document.getElementById('recipeModal').style.display = 'block';
}

function editRecipe(recipe) {
    document.getElementById('modalTitle').textContent = 'Edit Recipe';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('recipeId').value = recipe.id;
    document.getElementById('name').value = recipe.name;
    document.getElementById('description').value = recipe.description || '';
    document.getElementById('servings').value = recipe.servings;
    document.getElementById('profit_margin').value = recipe.profit_margin;
    originalServingsForEdit = parseFloat(recipe.servings) || 1;
    baseQuantityPerRowIndex = {};
    
    loadRecipeIngredients(recipe.id);
    
    document.getElementById('recipeModal').style.display = 'block';
}

async function loadRecipeIngredients(recipeId) {
    try {
        const response = await fetch(`api/recipe_ingredients.php?recipe_id=${recipeId}`);
        const data = await response.json();
        
        document.getElementById('ingredientsList').innerHTML = '';
        ingredientRowCount = 0;
        
        if (data.success && data.ingredients.length > 0) {
            data.ingredients.forEach(ingredient => {
                addIngredientRow(ingredient);
            });
        } else {
            addIngredientRow();
        }
    } catch (error) {
        console.error('Error loading recipe ingredients:', error);
        addIngredientRow();
    }
}

function addIngredientRow(ingredientData = null) {
    const ingredientsList = document.getElementById('ingredientsList');
    const rowIndex = ingredientRowCount;
    const rowId = `ingredient_${rowIndex}`;
    
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 10px;';
    
    row.innerHTML = `
        <div>
            <label>Ingredient</label>
            <select name="ingredients[${rowIndex}][ingredient_id]" required onchange="updateIngredientUnit(${rowIndex})">
                <option value="">Select Ingredient</option>
                ${ingredients.map(ing => `<option value="${ing.id}" ${ingredientData && ingredientData.ingredient_id == ing.id ? 'selected' : ''}>${ing.name}</option>`).join('')}
            </select>
        </div>
        <div>
            <label>Quantity</label>
            <input type="number" name="ingredients[${rowIndex}][quantity]" step="0.01" min="0" required 
                   value="${ingredientData ? parseFloat(ingredientData.quantity).toFixed(2) : ''}" 
                   oninput="if(this.value) this.value = parseFloat(this.value).toFixed(2)" 
                   onchange="calculateCosts()">
        </div>
        <div>
            <label>Unit</label>
            <select name="ingredients[${rowIndex}][unit]" required onchange="calculateCosts()">
                <option value="">Select Unit</option>
                ${units.map(unit => `<option value="${unit}" ${ingredientData && ingredientData.unit === unit ? 'selected' : ''}>${unit}</option>`).join('')}
            </select>
        </div>
        <div>
            <label>Cost</label>
            <input type="text" id="cost_${rowIndex}" readonly style="background: #f8f9fa;">
        </div>
        <div>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow('${rowId}')">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    row.id = rowId;
    row.dataset.rowIndex = String(rowIndex);
    ingredientsList.appendChild(row);
    ingredientRowCount++;
    
    const qtyInput = row.querySelector(`input[name="ingredients[${rowIndex}][quantity]"]`);
    const servingsInput = document.getElementById('servings');
    if (ingredientData && typeof ingredientData.quantity !== 'undefined') {
        const basePerServing = (parseFloat(ingredientData.quantity) || 0) / (originalServingsForEdit || 1);
        baseQuantityPerRowIndex[rowIndex] = basePerServing;
    }
    
    qtyInput.addEventListener('input', function() {
        const currentServings = parseFloat(servingsInput.value) || 1;
        const currentQty = parseFloat(qtyInput.value) || 0;
        baseQuantityPerRowIndex[rowIndex] = currentQty / currentServings;
    });
    
    if (ingredientData) {
        calculateCosts();
    }
}

function updateIngredientUnit(rowIndex) {
    const ingredientSelect = document.querySelector(`select[name="ingredients[${rowIndex}][ingredient_id]"]`);
    const unitSelect = document.querySelector(`select[name="ingredients[${rowIndex}][unit]"]`);
    
    if (ingredientSelect.value) {
        const ingredient = ingredients.find(ing => ing.id == ingredientSelect.value);
        if (ingredient) {
            unitSelect.value = ingredient.unit;
            calculateCosts();
        }
    }
}

function removeIngredientRow(rowId) {
    const row = document.getElementById(rowId);
    if (row) {
        const idx = row.dataset.rowIndex;
        if (idx && baseQuantityPerRowIndex.hasOwnProperty(idx)) {
            delete baseQuantityPerRowIndex[idx];
        }
        row.remove();
        calculateCosts();
    }
}

function calculateCosts() {
    const servings = parseFloat(document.getElementById('servings').value) || 1;
    const profitMargin = parseFloat(document.getElementById('profit_margin').value) || 0;
    
    let totalCost = 0;
    
    document.querySelectorAll('.ingredient-row').forEach((row, index) => {
        const ingredientSelect = row.querySelector('select[name*="[ingredient_id]"]');
        const quantityInput = row.querySelector('input[name*="[quantity]"]');
        const unitSelect = row.querySelector('select[name*="[unit]"]');
        const costInput = row.querySelector('input[id^="cost_"]');
        
        if (ingredientSelect.value && quantityInput.value && unitSelect.value) {
            const ingredient = ingredients.find(ing => ing.id == ingredientSelect.value);
            if (ingredient) {
                const quantity = parseFloat(quantityInput.value) || 0;
                const convertedQuantity = convertUnit(quantity, unitSelect.value, ingredient.unit);
                const cost = convertedQuantity * ingredient.price_per_unit;
                
                costInput.value = '₱' + cost.toFixed(2);
                totalCost += cost;
            }
        }
    });
    
    const totalCostElement = document.getElementById('totalCost');
    if (totalCostElement) {
        totalCostElement.textContent = '₱' + totalCost.toFixed(2);
    }
    
    const costPerServing = totalCost / servings;
    const costPerServingElement = document.getElementById('costPerServing');
    if (costPerServingElement) {
        costPerServingElement.textContent = '₱' + costPerServing.toFixed(2);
    }
    
    const suggestedPrice = costPerServing * (1 + (profitMargin / 100));
    const suggestedPriceElement = document.getElementById('suggestedPrice');
    if (suggestedPriceElement) {
        suggestedPriceElement.textContent = '₱' + suggestedPrice.toFixed(2);
    }
}

function scaleQuantitiesOnServingsChange() {
    const servingsInput = document.getElementById('servings');
    const newServings = Math.max(1, parseFloat(servingsInput.value) || 1);

    if (!originalServingsForEdit || originalServingsForEdit <= 0) {
        originalServingsForEdit = newServings;
    }

    document.querySelectorAll('.ingredient-row').forEach((row) => {
        const idx = row.dataset.rowIndex;
        const qtyInput = row.querySelector('input[name*="[quantity]"]');
        if (!qtyInput) return;

        let basePerServing;

        if (idx && baseQuantityPerRowIndex.hasOwnProperty(idx)) {
            basePerServing = baseQuantityPerRowIndex[idx];
        } else {
            const currentQty = parseFloat(qtyInput.value) || 0;
            basePerServing = currentQty / (originalServingsForEdit || 1);
            if (idx) baseQuantityPerRowIndex[idx] = basePerServing;
        }

        const newQty = basePerServing * newServings;
        qtyInput.value = (isFinite(newQty) ? newQty : 0).toFixed(2);
    });

    originalServingsForEdit = newServings;

    calculateCosts();
}

function convertUnit(quantity, fromUnit, toUnit) {
    if (fromUnit === toUnit) {
        return quantity;
    }
    
    const conversions = {
        'g': { 'kg': 0.001, 'lb': 0.00220462, 'oz': 0.035274 },
        'kg': { 'g': 1000, 'lb': 2.20462, 'oz': 35.274 },
        'ml': { 'l': 0.001, 'cup': 0.00422675, 'tbsp': 0.067628, 'tsp': 0.202884 },
        'l': { 'ml': 1000, 'cup': 4.22675, 'tbsp': 67.628, 'tsp': 202.884 }
    };
    
    if (conversions[fromUnit] && conversions[fromUnit][toUnit]) {
        return quantity * conversions[fromUnit][toUnit];
    }
    
    return quantity;
}

function closeModal() {
    document.getElementById('recipeModal').style.display = 'none';
    document.getElementById('recipeForm').reset();
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    submitBtn.innerHTML = '<span class="loading"></span> Saving...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('recipes.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: result.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.message || 'Failed to save recipe'
            });
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
        
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving the recipe'
        });
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

async function deleteRecipe(id) {
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
            
            const response = await fetch('recipes.php', {
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
                text: 'An error occurred while deleting the recipe'
            });
        }
    }
}

async function viewRecipe(id) {
    try {
        const response = await fetch(`api/recipe_details.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            displayRecipeDetails(data.recipe);
            document.getElementById('viewModal').style.display = 'block';
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to load recipe details'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while loading recipe details'
        });
    }
}

function displayRecipeDetails(recipe) {
    const detailsDiv = document.getElementById('recipeDetails');
    
    detailsDiv.innerHTML = `
        <div class="recipe-details">
            <div style="display:flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px;">
                <button class="btn btn-primary btn-sm" onclick="exportRecipeDetails(${recipe.id})">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
            <h3>${recipe.name}</h3>
            <p><strong>Description:</strong> ${recipe.description || 'No description'}</p>
            <p><strong>Servings:</strong> ${recipe.servings}</p>
            <p><strong>Profit Margin:</strong> ${recipe.profit_margin}%</p>
            
            <h4>Computation Details:</h4>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Recipe Qty</th>
                            <th>Converted Qty</th>
                            <th>Price/Unit</th>
                            <th>Line Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${recipe.ingredients.map(ing => `
                            <tr>
                                <td>${ing.ingredient_name}</td>
                                <td>${Number(ing.recipe_quantity).toFixed(2)} ${ing.recipe_unit}</td>
                                <td>${Number(ing.converted_quantity).toFixed(2)} ${ing.ingredient_unit}</td>
                                <td>₱${Number(ing.price_per_unit).toFixed(2)} / ${ing.ingredient_unit}</td>
                                <td>₱${Number(ing.cost).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="cost-summary">
                <h4>Cost Summary:</h4>
                <p><strong>Total Cost:</strong> ₱${Number(recipe.total_cost).toFixed(2)}</p>
                <p><strong>Cost per Serving:</strong> ₱${Number(recipe.cost_per_serving).toFixed(2)}</p>
                <p><strong>Suggested Price (per serving):</strong> ₱${Number(recipe.suggested_price).toFixed(2)}</p>
                <p><strong>Profit per Serving:</strong> <span style="color: #27ae60; font-weight: 600;">₱${Number(recipe.profit_per_serving).toFixed(2)}</span></p>
                <p><strong>Total Profit (${recipe.servings} servings):</strong> <span style="color: #27ae60; font-weight: 600;">₱${Number(recipe.total_profit).toFixed(2)}</span></p>
            </div>
        </div>
    `;
}

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

window.onclick = function(event) {
    const recipeModal = document.getElementById('recipeModal');
    const viewModal = document.getElementById('viewModal');
    
    if (event.target === recipeModal) {
        closeModal();
    }
    if (event.target === viewModal) {
        closeViewModal();
    }
}

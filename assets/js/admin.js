document.addEventListener('DOMContentLoaded', function() {
    // Toggle between list and editor views
    const addNewBtn = document.getElementById('pc-add-new');
    const carouselList = document.querySelector('.pc-carousel-list');
    const carouselEditor = document.querySelector('.pc-carousel-editor');

    if (addNewBtn && carouselList && carouselEditor) {
        addNewBtn.addEventListener('click', function() {
            carouselList.style.display = 'none';
            carouselEditor.style.display = 'block';
            resetEditor();
        });

        document.getElementById('pc-cancel-edit').addEventListener('click', function() {
            carouselList.style.display = 'block';
            carouselEditor.style.display = 'none';
        });
    }

    function resetEditor() {
    document.getElementById('pc-carousel-name').value = '';
    document.getElementById('pc-carousel-slug').value = '';
    document.querySelector('.pc-carousel-editor').dataset.id = '';
    
    // Reset settings to defaults
    document.getElementById('pc-desktop-columns').value = '5';
    document.getElementById('pc-mobile-columns').value = '2';
    document.getElementById('pc-visible-mobile').value = '2';
    document.getElementById('pc-order-by').value = 'popular';
    document.getElementById('pc-products-per-page').value = '10';
    
    // Reset category select
    const categorySelect = jQuery('.pc-category-select');
    categorySelect.val(null).trigger('change');
    
    // Add this to reset discount rule
    const discountRuleSelect = jQuery('.pc-discount-rule-select');
    discountRuleSelect.val(null).trigger('change');
}

    // Initialize Select2 for category selection
jQuery(document).ready(function($) {
    $('.pc-category-select').select2({
        placeholder: pc_admin_vars.translations.select_category,
        allowClear: true,
        data: pc_admin_vars.categories
    });
    
    // Add this for discount rules
    $('.pc-discount-rule-select').select2({
        placeholder: pc_admin_vars.translations.select_rule,
        allowClear: true,
        data: pc_admin_vars.discount_rules
    });
});

  // Add rule selection handling
document.getElementById('pc-category').addEventListener('change', function() {
    if(this.value) document.getElementById('pc-discount-rule').value = '';
});

document.getElementById('pc-discount-rule').addEventListener('change', function() {
    if(this.value) document.getElementById('pc-category').value = '';
});

    // Handle Edit Carousel
    document.addEventListener('click', async function(e) {
        const editBtn = e.target.closest('.pc-edit-carousel');
        if (editBtn) {
            const carouselId = editBtn.dataset.id;
            
            try {
                const response = await fetch(pc_admin_vars.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'pc_get_carousel',
                        id: carouselId,
                        nonce: pc_admin_vars.nonce
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.data || 'Failed to load carousel');
                }

                // Populate editor with carousel data
                const carousel = data.data;
                document.getElementById('pc-carousel-name').value = carousel.name;
                document.getElementById('pc-carousel-slug').value = carousel.slug;
                document.querySelector('.pc-carousel-editor').dataset.id = carousel.id;

                // Load settings
                document.getElementById('pc-desktop-columns').value = carousel.settings.desktop_columns;
                document.getElementById('pc-mobile-columns').value = carousel.settings.mobile_columns;
                document.getElementById('pc-visible-mobile').value = carousel.settings.visible_mobile;
                document.getElementById('pc-order-by').value = carousel.settings.order_by;
                document.getElementById('pc-products-per-page').value = carousel.settings.products_per_page;

                // Load category
                if (carousel.settings.category) {
                    jQuery('.pc-category-select').val(carousel.settings.category).trigger('change');
                }
              // Add this to load discount rule
if (carousel.settings.discount_rule) {
    jQuery('.pc-discount-rule-select').val(carousel.settings.discount_rule).trigger('change');
}

                // Show editor
                carouselList.style.display = 'none';
                carouselEditor.style.display = 'block';

            } catch (error) {
                console.error('Load error:', error);
                alert(`Failed to load carousel: ${error.message}`);
            }
        }

        // Handle Delete Carousel
        const deleteBtn = e.target.closest('.pc-delete-carousel');
        if (deleteBtn) {
            e.preventDefault();
            const carouselId = deleteBtn.dataset.id;
            
            if (confirm('Are you sure you want to delete this carousel?')) {
                try {
                    const response = await fetch(pc_admin_vars.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'pc_delete_carousel',
                            id: carouselId,
                            nonce: pc_admin_vars.nonce
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        window.location.reload();
                    } else {
                        throw new Error(data.data || 'Delete failed');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    alert('Delete failed: ' + error.message);
                }
            }
        }
    });
    
    // Save carousel handler
    const saveButton = document.getElementById('pc-save-carousel');
    if (saveButton) {
        saveButton.addEventListener('click', async function() {
            const saveButton = this;
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';

            try {
                // Get settings data
                const settings = {
    desktop_columns: document.getElementById('pc-desktop-columns').value,
    mobile_columns: document.getElementById('pc-mobile-columns').value,
    visible_mobile: document.getElementById('pc-visible-mobile').value,
    category: jQuery('.pc-category-select').val() || '',
    discount_rule: jQuery('.pc-discount-rule-select').val() || '', // Add this line
    order_by: document.getElementById('pc-order-by').value,
    products_per_page: document.getElementById('pc-products-per-page').value
};

                const formData = new FormData();
                formData.append('action', 'pc_save_carousel');
                formData.append('nonce', pc_admin_vars.nonce);
                formData.append('carousel_id', document.querySelector('.pc-carousel-editor').dataset.id || '');
                formData.append('name', document.getElementById('pc-carousel-name').value);
                formData.append('slug', document.getElementById('pc-carousel-slug').value);
                formData.append('settings', JSON.stringify(settings));

                const response = await fetch(pc_admin_vars.ajax_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.data || 'Save failed');
                }

                alert('Carousel saved successfully!');
                window.location.reload();

            } catch (error) {
                console.error('Save error:', error);
                alert(`Failed to save carousel: ${error.message}`);
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'Save Carousel';
            }
        });
    }
    
    // Generate slug from name
    const nameInput = document.getElementById('pc-carousel-name');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const slugInput = document.getElementById('pc-carousel-slug');
            if (!slugInput.value) {
                slugInput.value = this.value.toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            }
        });
    }
});
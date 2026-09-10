/**
 * Javascript for OptiPub archive pages
 * 
 * @package optipub
 * @since 1.0.0
 */

function toggleSearchButton() {
    var element = document.getElementById('archives-filters');
    var search_button = document.getElementById('archives-filters-search');

        if (search_button) {
        if (window.getComputedStyle(element).display !== 'none') {
            console.log('hiding search');
            search_button.classList.remove('d-flex');
            search_button.classList.add('d-none');
        } else {
            console.log('showing search');
            search_button.classList.add('d-flex');
            search_button.classList.remove('d-none');
        }
    }
}

function toggleFilters(toggle = '', event) {
    if (typeof event !== 'undefined') {
        event.preventDefault();
    }

    var element = document.getElementById('archives-filters');
    const elementIsVisible = $(element).is(':visible');

    element.classList.remove('d-lg-flex');
    if (toggle == 'show') {
        console.log('showing filters');
        element.classList.remove('d-lg-flex');
        element.classList.add('d-flex');
        element.classList.remove('d-none');
    } else if (elementIsVisible || toggle == 'hide') {
        console.log('hiding filters');
        element.classList.remove('d-lg-flex');
        element.classList.remove('d-flex');
        element.classList.add('d-none');
    } else if (event) {
        element.classList.add('d-flex');
        element.classList.remove('d-none');
    }

    toggleSearchButton();
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to the form's submit event
    document.getElementById('archives-filters-form').addEventListener('submit', function(e) {
        // Find all select elements within the form
        const selects = this.querySelectorAll('select');
        
        // Iterate over each select element
        selects.forEach(function(select) {
            // If the select's value is empty, disable it
            if (select.value === '') {
                select.disabled = true;
            }
        });
    });
});

window.onload = function () {
    console.log('selected' + filtersSelectedEmpty);
    console.log('search' + searchEnabled);
    if(!filtersSelectedEmpty) {
        toggleFilters('show');
    } else if (searchEnabled) {
        toggleFilters('hide');
    }

    var element = document.getElementById('card-filters');
    element.classList.remove('d-none');
}



(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var list = document.getElementById('tn-qr-extra-fields');
        var add = document.getElementById('tn-qr-add-extra');
        if (!list || !add) {
            return;
        }

        add.addEventListener('click', function() {
            if (list.children.length >= 20) {
                return;
            }
            var row = list.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(function(input) {
                input.value = '';
            });
            list.appendChild(row);
            row.querySelector('input').focus();
        });

        list.addEventListener('click', function(event) {
            if (!event.target.classList.contains('tn-qr-remove-extra')) {
                return;
            }
            var row = event.target.closest('.tn-qr-extra-row');
            if (list.children.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(function(input) {
                    input.value = '';
                });
            }
        });
    });
})();

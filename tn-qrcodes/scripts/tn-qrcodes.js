(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var img = document.getElementById('tn-qr-image');
        var refresh = document.getElementById('tn-qr-refresh');
        var download = document.getElementById('tn-qr-download');
        var postId = document.getElementById('tn-qr-post-id');
        var nonce = document.getElementById('tn-qr-ajax-nonce');
        var sourceEl = document.getElementById('tn_qr_utm_source');
        var mediumEl = document.getElementById('tn_qr_utm_medium');
        var campaignEl = document.getElementById('tn_qr_utm_campaign');

        if (!img || !refresh || !download || !postId || !nonce || !sourceEl || !mediumEl || !campaignEl) {
            return;
        }

        var timer = null;

        function updateQR(forceRefresh) {
            var formData = new FormData();

            formData.append('action', 'tn_qr_preview');
            formData.append('nonce', nonce.value);
            formData.append('post_id', postId.value);
            formData.append('utm_source', sourceEl.value);
            formData.append('utm_medium', mediumEl.value);
            formData.append('utm_campaign', campaignEl.value);
            formData.append('force_refresh', forceRefresh ? '1' : '');

            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (!data || !data.success || !data.data) {
                        return;
                    }

                    img.src = data.data.qr_url;
                    download.href = data.data.download_url;
                })
                .catch(function() {});
        }

        function scheduleUpdate() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                updateQR(false);
            }, 300);
        }

        sourceEl.addEventListener('input', scheduleUpdate);
        mediumEl.addEventListener('input', scheduleUpdate);
        campaignEl.addEventListener('input', scheduleUpdate);

        refresh.addEventListener('click', function() {
            updateQR(true);
        });
    });
})();

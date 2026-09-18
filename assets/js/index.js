(function() {
    var tripSelect = document.querySelector('[data-quick-plan-existing-trip]');
    if (!tripSelect) {
        return;
    }

    function selectExistingTripOption() {
        var choice = tripSelect.closest('.quick-plan-choice');
        var radio = choice ? choice.querySelector('input[type="radio"]') : null;
        if (radio) {
            radio.checked = true;
        }
    }

    tripSelect.addEventListener('focus', selectExistingTripOption);
    tripSelect.addEventListener('change', selectExistingTripOption);
}());

(function() {
    var dropZone = document.getElementById('itinerary_drop_zone');
    var fileInput = document.getElementById('itinerary_file');
    var fileName = document.getElementById('itinerary_file_name');

    if (!dropZone || !fileInput || !fileName) {
        return;
    }

    function showFileName() {
        fileName.textContent = fileInput.files && fileInput.files.length
            ? fileInput.files[0].name
            : (window.travelAppIndexData && window.travelAppIndexData.fileLabel) || 'ICS or text file';
    }

    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function(event) {
            event.preventDefault();
            dropZone.classList.add('dragging');
        });
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
        dropZone.addEventListener(eventName, function(event) {
            event.preventDefault();
            dropZone.classList.remove('dragging');
        });
    });

    dropZone.addEventListener('drop', function(event) {
        if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
            fileInput.files = event.dataTransfer.files;
            showFileName();
        }
    });

    fileInput.addEventListener('change', showFileName);
}());

(function() {
    var control = document.querySelector('[data-calendar-subscription]');
    if (!control) {
        return;
    }

    var button = control.querySelector('[data-copy-url]');
    var status = control.querySelector('[data-copy-status]');
    if (!button) {
        return;
    }

    function confirmCopied() {
        button.textContent = (window.travelAppIndexData && window.travelAppIndexData.copied) || 'Copied!';
        if (status) {
            status.textContent = (window.travelAppIndexData && window.travelAppIndexData.calendarCopied) || 'Calendar subscription link copied.';
        }
        window.setTimeout(function() {
            button.textContent = (window.travelAppIndexData && window.travelAppIndexData.copyUrl) || 'Copy URL';
        }, 1800);
    }

    button.addEventListener('click', function() {
        var url = button.getAttribute('data-copy-url') || '';
        if (!url) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(confirmCopied).catch(function() {
                window.prompt((window.travelAppIndexData && window.travelAppIndexData.copyPrompt) || 'Copy this link:', url);
                confirmCopied();
            });
            return;
        }

        window.prompt((window.travelAppIndexData && window.travelAppIndexData.copyPrompt) || 'Copy this link:', url);
        confirmCopied();
    });
}());

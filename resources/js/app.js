(() => {
    // DOM Elements
    const fileInput = document.querySelector('#file-input');
    const dropZone = document.querySelector('#drop-zone');
    const chooseButton = document.querySelector('#choose-button');
    const fileStatus = document.querySelector('#file-status');

    const selectedCard = document.querySelector('#selected-card');
    const selectedFilename = document.querySelector('#selected-filename');
    const selectedFilesize = document.querySelector('#selected-filesize');
    const convertButton = document.querySelector('#convert-button');
    const changeFileButton = document.querySelector('#change-file-button');

    const convertingCard = document.querySelector('#converting-card');
    const successCard = document.querySelector('#success-card');
    const successFilename = document.querySelector('#success-filename');
    const downloadLink = document.querySelector('#download-link');
    const convertAnotherButton = document.querySelector('#convert-another-button');

    const errorCard = document.querySelector('#error-card');
    const errorTitle = document.querySelector('#error-title');
    const errorMessage = document.querySelector('#error-message');
    const errorInstructions = document.querySelector('#error-instructions');
    const instructionsList = document.querySelector('#instructions-list');
    const tryAgainButton = document.querySelector('#try-again-button');

    if (!fileInput || !dropZone || !chooseButton) {
        return;
    }

    let currentFile = null;
    let activeBlobUrl = null;

    const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25 MB

    const formatBytes = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    const setStatusLive = (msg) => {
        if (fileStatus) {
            fileStatus.textContent = msg;
        }
    };

    const showView = (view) => {
        // Hide all views first
        dropZone.classList.add('hidden');
        if (selectedCard) selectedCard.classList.add('hidden');
        if (convertingCard) convertingCard.classList.add('hidden');
        if (successCard) successCard.classList.add('hidden');
        if (errorCard) errorCard.classList.add('hidden');

        if (view === 'drop') {
            dropZone.classList.remove('hidden');
            setStatusLive('Ready to accept Word document.');
        } else if (view === 'selected') {
            if (selectedCard) selectedCard.classList.remove('hidden');
            setStatusLive(`${currentFile?.name} selected. Ready to convert.`);
        } else if (view === 'converting') {
            if (convertingCard) convertingCard.classList.remove('hidden');
            setStatusLive('Converting document. Please wait.');
        } else if (view === 'success') {
            if (successCard) successCard.classList.remove('hidden');
            setStatusLive('Conversion complete. PDF downloaded.');
        } else if (view === 'error') {
            if (errorCard) errorCard.classList.remove('hidden');
            setStatusLive('Conversion failed.');
        }
    };

    const resetSelection = () => {
        currentFile = null;
        fileInput.value = '';
        if (activeBlobUrl) {
            URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = null;
        }
        showView('drop');
    };

    const showError = (title, message, instructions = []) => {
        if (errorTitle) errorTitle.textContent = title || 'Conversion Error';
        if (errorMessage) errorMessage.textContent = message || 'An error occurred.';

        if (errorInstructions && instructionsList) {
            if (instructions && instructions.length > 0) {
                instructionsList.innerHTML = '';
                instructions.forEach((step) => {
                    const li = document.createElement('li');
                    li.textContent = step;
                    instructionsList.appendChild(li);
                });
                errorInstructions.classList.remove('hidden');
            } else {
                instructionsList.innerHTML = '';
                errorInstructions.classList.add('hidden');
            }
        }

        showView('error');
    };

    const handleFile = (file) => {
        if (!file) return;

        const lowerName = file.name.toLowerCase();
        const isDocOrDocx = lowerName.endsWith('.docx') || lowerName.endsWith('.doc');

        if (!isDocOrDocx) {
            showError('Invalid File Format', 'Please upload a Word document with a .doc or .docx extension.');
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            showError('File Too Large', `The selected file is ${formatBytes(file.size)}. Maximum allowed size is 25MB.`);
            return;
        }

        currentFile = file;

        if (selectedFilename) selectedFilename.textContent = file.name;
        if (selectedFilesize) selectedFilesize.textContent = formatBytes(file.size);

        showView('selected');
    };

    const getCsrfToken = () => {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    };

    const convertDocument = async () => {
        if (!currentFile) {
            showError('No File Selected', 'Please select a Word file to convert.');
            return;
        }

        showView('converting');

        const formData = new FormData();
        formData.append('file', currentFile);

        try {
            const response = await fetch('/convert', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/pdf, application/json',
                },
                body: formData,
            });

            if (response.ok) {
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/pdf')) {
                    const blob = await response.blob();
                    if (activeBlobUrl) {
                        URL.revokeObjectURL(activeBlobUrl);
                    }
                    activeBlobUrl = URL.createObjectURL(blob);

                    // Determine output filename
                    let targetFilename = 'document.pdf';
                    const disposition = response.headers.get('content-disposition');
                    if (disposition && disposition.includes('filename=')) {
                        const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                        if (matches != null && matches[1]) {
                            targetFilename = matches[1].replace(/['"]/g, '');
                        }
                    } else if (currentFile && currentFile.name) {
                        targetFilename = currentFile.name.replace(/\.[^/.]+$/, '') + '.pdf';
                    }

                    // Setup download links
                    if (downloadLink) {
                        downloadLink.href = activeBlobUrl;
                        downloadLink.setAttribute('download', targetFilename);
                    }
                    if (successFilename) {
                        successFilename.textContent = `${targetFilename} is ready to download.`;
                    }

                    // Auto-trigger browser download
                    const autoDownload = document.createElement('a');
                    autoDownload.href = activeBlobUrl;
                    autoDownload.download = targetFilename;
                    document.body.appendChild(autoDownload);
                    autoDownload.click();
                    autoDownload.remove();

                    showView('success');
                    return;
                }
            }

            // Error response
            let errorData = null;
            try {
                errorData = await response.json();
            } catch (_) {
                // Not JSON
            }

            const status = response.status;
            let errTitle = 'Conversion Failed';
            let errMessage = errorData?.message || 'Could not convert document. Please verify the file and try again.';
            let instructions = errorData?.instructions || [];

            if (status === 503 || errorData?.error_code === 'LIBREOFFICE_NOT_INSTALLED') {
                errTitle = 'Server Setup Required';
            } else if (status === 422) {
                errTitle = 'Validation Error';
            }

            showError(errTitle, errMessage, instructions);
        } catch (err) {
            showError(
                'Network Error',
                'Unable to communicate with the server. Please check your connection and try again.'
            );
        }
    };

    // Event Listeners
    chooseButton.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.click();
    });

    dropZone.addEventListener('click', (e) => {
        if (e.target === dropZone) {
            fileInput.click();
        }
    });

    dropZone.addEventListener('keydown', (e) => {
        if (e.target === dropZone && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            fileInput.click();
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files[0]) {
            handleFile(fileInput.files[0]);
        }
    });

    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('is-dragging');
    });

    dropZone.addEventListener('dragleave', (e) => {
        if (!dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('is-dragging');
        }
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('is-dragging');
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    // Buttons
    if (convertButton) {
        convertButton.addEventListener('click', convertDocument);
    }

    if (changeFileButton) {
        changeFileButton.addEventListener('click', resetSelection);
    }

    if (convertAnotherButton) {
        convertAnotherButton.addEventListener('click', resetSelection);
    }

    if (tryAgainButton) {
        tryAgainButton.addEventListener('click', resetSelection);
    }
})();
